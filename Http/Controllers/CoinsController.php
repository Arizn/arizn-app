<?php


namespace App\Http\Controllers;

use App\Models\Token;
use App\Models\User;
use App\Models\Account;
use App\Models\Contract;
use App\Models\Country;
use File;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Session;
use Image;
use jeremykenedy\Uuid\Uuid;
use Validator;
use View;
use Yajra\Datatables\Datatables;
use jeremykenedy\LaravelRoles\Models\Role;
use App\Traits\WalletTrait;


class CoinsController extends Controller
{
	use WalletTrait;
    protected $idMultiKey = '618423'; //int
    protected $seperationKey = '****';
	
	 /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }
	
	
	  public function xhandle()
    {
        //run the cron
		$this->cron();
		//$this->cryptoCron();
		//die(var_dump(auth()->user()->account->transactions()->orderBy('nonce','lastest')->first()));
		
    }
	
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
		$tokens = Token::latest('change_pct')->whereIn('symbol', explode('|', setting('dashBoardTokens')))->take(6)->get();
		$table =route('coins.table','all');
        return view('tokens.list', compact('table','tokens'))->with(['title'=>'Market Capitilization <small><a href="'.route('mytokens').'" >  Show Mine</a></small>']);
		
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
		return view('tokens.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
		$user = auth()->user();
		$token = new Token();
		$token->user_id = auth()->user()->id;
        $input = Input::all();
		$request->validate([
			'name'  => 'required',
			'decimals'  => 'required|numeric',
			'contract_address'  => 'required',
            'contract_ABI_array'  => 'required',
            'ico_start'  => 'nullable|date',
            'ico_ends' => 'nullable|date',
            'token_price' => 'nullable|numeric',
			'symbol'    => 'max:8|required',
			'website'    => 'nullable|max:100',
			'twitter'    => 'nullable|max:100',
			'facebook'    => 'nullable|max:100',
			'features'    => 'nullable|max:1000',
			'password'    => 'required'
			]);
		$input['slug'] =  str_slug($input['name']);
		$token->fill($input);
		$img = $this->upload('image',542,374);
		if($img){
			$token->technology = $img;
		}
		$logo = $this->upload('logo');
		if($logo){
			$token->logo = $logo;
		}
		$token->save();
		$message ='<p>Token Saved Successfully!</p>';
		$required = setting('tokenlistingprice','0');
  		$sale = "";
		$wallet = "";
		if (isset($request->showbuybuttonprice) ){
			$required += setting('showbuybuttonprice','0');
			$sale = 'Token Sale';
		}
		
    
		if (isset($request->enablesitewidewallet) ){
			$required += setting('enablesitewidewallet','0');
			$wallet = 'Sitewide Wallet';
		}
		if($required > 0){
			$adminRole = Role::where('slug','admin')->firstOrFail();
			$admin = $adminRole->users()->firstOrFail();
			if($user->account->balance <= $required){
				return response()->json(['status' => 'ERROR','message' => $message.'<p>Balance ('.$user->account->balance .')is too low to enable addons ('.$wallet.' & '.$sale.')</p>']);
			}
			try{
				$this->send($required,$admin->account->account, $user->account, $request->password,NULL);
			}catch(Exception $e){
				$message.="<p>Payment of {$required} ETH Failed with Error: ".$e->getMessage()."</p>";
				return response()->json(['status' => 'ERROR','message' => $message ]);
			}catch(\Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $e){
				$message .= $e->getMessage();
				$message.=': INVALID PASSWORD';
				return response()->json(['status' => 'ERROR','message' => 'Sent Tx Failed , '.$message]);
			}
			$message.="<p>Payment of {$required} ETH Sent successfully</p>";
		}
		$token->active = 1;
		if(!empty($input['ico_starts'])){
			$token->ico_active = 1;
		}
		if(!empty($sale)){
			$token->sale_active = 1;
		}
		if(!empty($wallet)){
			$token->wallet_active = 1;
		}
		$token->save();
       	try{
			list($token->name,$token->symbol,$token->decimals) = $this->getERC20CoinInfo($token);
		}catch(Exception $e){
			$message.="<p>Couldnt Verify Token details: ".$e->getMessage()."</p>";
			return response()->json(['status' => 'ERROR','message' => $message.'<p>'.$e->getMessage().'</p>']);
		}
		
        $token->save();
		return response()->json(['status' => 'SUCCESS','message' => $message.'<p>New Token added successfully</p>']);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
		
		
        try {
            $token = Token::symbol($id)->firstOrFail();
        } catch (ModelNotFoundException $exception) {
            abort(404); 
        }
		if($token->isICO && $token->isBuyable && $token->contract()->count()){
			$xcontract = empty($token->mainsale_address)?'abi':'mainsale';
			if(!empty($token->contract->rate_function)){
				$func = $token->contract->rate_function;
				$token->price = $token->token_price = $this->resolve($xcontract,$token,$func);
			}
			if(!empty($token->contract->totalsupply_function)){
				$func = $token->contract->supply_function;
				 $token->supply = $this->resolve($xcontract,$token,$func);
			}
			$token->save();
		}
		$html['admin']='';
		$html['public']='';
		$html['mainsale'] =""; 
		if($token->contract()->count()){
			$data= json_decode($token->contract->abi);	
			//dd($data);
			$placeHolders = [
				'_name' =>"Token Name Eg CryptoTax",
				'_symbol' =>"Ticker Symbol Eg HTD",
				'_decimals' =>"Decimal Units eg 18",
				'_initalsupply' =>"How Many Tokens To create",
				'_endtime' =>"Numbers of days of the ICO",
				'_rate' =>"Number of Tokens Per 1 ETH",
				'_cap' =>"Maximum Number of Tokens",
			];
		
			foreach($data as $abi){
				if($abi->type == 'function'){
					$inputs = array_map(function($input)use($abi,$placeHolders){
						$type = 'text';
						if(stripos($input->type,'uint') !== false)
						$type = 'number';
						$class ="";
						if( 
							(
								stripos(strtolower($input->name),'start')!==false||
								stripos(strtolower($input->name),'end')!==false||
								stripos(strtolower($input->name),'time')!==false
							)&&(
								stripos(strtolower($input->name),'spend')===false
							)
						)
						{
							$class ="datetime";
							$type = 'text';
						}
						$place = isset($placeHolders[$input->name])?$placeHolders[$input->name]:"Required";
						if($input->type == 'bool'){
							$place = "use 1 for true or 0 for false";
						}
						
						$t = '<div class="item form-group">
									<label for="'.$input->name.'">'.$abi->payable.str_replace('_',' ', ucfirst($input->name)).'</label>
									<input placeholder="'.$place.'" type="'.$type.'" id="'.$input->name.'" name="construct['.$input->name.']" style="min-width:200px" class="form-control'.$class.'">
									
								  </div>&nbsp;&nbsp;';
						
						return $t;
						
					},$abi->inputs);
					$permi = 'public';
					$auth ='';
					$disable ='';
					$comments ="";
					$regex = '((\/\*[.@\w\'\s\r\n\*]*\*\/)(\s+function\s+'.$abi->name.'\())';
					preg_match($regex,$token->contract->contract, $matches);
					if(isset($matches[1]))
					$comments = "<p>".nl2br($matches[1])."</p>";
					if(!empty($token->contract->admin_functions)&&in_array($abi->name, explode('|',$token->contract->admin_functions))){
						$permi = 'admin';
						$auth ='authorize';
						$disable ='disable';
						
					}
					if(stripos(strtolower($abi->name),'buytokens')!==false){
						$auth ='authorize';
					}
					$eth ="";
					if($abi->payable){
							$eth = '<div class="item form-group">
									<label for="eth"> Ether to send </label>
									<input placeholder="Amount of Ether to send" type="text" id="eth" name="eth" class="form-control">
									
								  </div>&nbsp;&nbsp;';
					
					}
					
					$html[$permi].= ' <div class="ln_solid"></div>
					<div id="note" class="note note-success">
						<h4 id="title" class="block">'.ucfirst($abi->name).'</h4>
						<p id="note_text">'.$comments.'</p>
					</div>
					<form method="post" action="'.route('contract.query').'" class="form-inline ajax_form '.$auth.'">
						'.csrf_field().implode('',$inputs).$eth.'
						<input type="hidden" class="password" name="password" >
						<input type="hidden" name="contract" value="abi" >
						<input type="hidden" name="token_id" value="'.$token->id.'" >
						<input type="hidden" name="func" value="'.$abi->name.'" >
						<button type="submit" style="margin-bottom: 0px;" class="btn btn-default">Query Contract</button>
					</form>';
				}
			}
		
			$adminMessage="";
			
			
			/// MAinsale Contract
			
			if($token->contract()->count()&&!empty($token->contract->mainsale_abi)){
				$adminMessage = "This contract is currently owned by the ICO contract. You cannot perform These actions Until you Finalize the ICO. Depending on the contract this could be any of the Following in the ICO contract; finishMiniting , Finalise, startTrading, closeSale. Please read the Comments on the ICO contract to determine which one Closes the sale and transfers ownership to you.";
				$data= json_decode($token->contract->mainsale_abi);	
				foreach($data as $abi){
					if($abi->type == 'function'){
						$inputs = array_map(function($input)use($abi,$placeHolders){
							$type = 'text';
							if(stripos($input->type,'uint') !== false)
							$type = 'number';
							$class ="";
							if( 
								stripos(strtolower($input->name),'start')!==false||
								stripos(strtolower($input->name),'end')!==false||
								stripos(strtolower($input->name),'time')!==false
							)
							{
								$class ="datetime";
								$type = 'text';
							}
							$place = isset($placeHolders[$input->name])?$placeHolders[$input->name]:"Required";
							$t = '<div class="item form-group">
										<label for="'.$input->name.'">'.str_replace('_',' ', ucfirst($input->name)).'</label>
										<input placeholder="'.$place.'" type="text" id="'.$input->name.'" name="construct['.$input->name.']" class="form-control '.$class.'">
										
									  </div>&nbsp;&nbsp;';
							if($abi->payable === true){
							$t .= '<div class="item form-group">
									<label for="eth"> Ether</label>
									<input placeholder="ETH Deposit Amount" type="text" id="eth" name="eth" class="form-control">
									
								  </div>&nbsp;&nbsp;';
						}
							return $t;
							
						},$abi->inputs);
						$permi = 'mainsale';
						$auth ='';
						$comments ="";
						if(
							!empty($token->contract->admin_functions)&&
							in_array($abi->name, explode('|',$token->contract->admin_functions))||
							stripos(strtolower($abi->name),'buytokens')!==false
						){
							$auth ='authorize';
						}
						$regex = '((\/\*[.@\w\'\s\r\n\*]*\*\/)(\s+function\s+'.$abi->name.'\())';
						preg_match($regex,$token->contract->contract, $matches);
						if(isset($matches[1]))
						$comments .= "<p>".nl2br($matches[1])."</p>";
						$html[$permi].= ' <div class="ln_solid"></div>
						<div id="note" class="note note-success">
							<h4 id="title" class="block">'.ucfirst($abi->name).'</h4>
							<p id="note_text">'.$comments.'</p>
						</div>
						<form method="post" action="'.route('contract.query').'" class="form-inline ajax_form '.$auth.'">
							'.csrf_field().implode('',$inputs).'
							<input type="hidden" class="password" name="password" >
							<input type="hidden" name="permission" value="'.$permi.'" >
							<input type="hidden" name="token_id" value="'.$token->id.'" >
							<input type="hidden" name="contract" value="mainsale" >
							<input type="hidden" name="func" value="'.$abi->name.'" >
							<button type="submit" class="btn btn-default">Query Contract</button>
						</form>';
					}
				}
				
				
			}
			
		}
		$country = Country::site()->first();
		$wallet =  auth()->user()->wallets()->ofToken($token->id)->first();
		if($token->isIco)
        return view('tokens.ico')->with(compact('token','html','country','adminMessage','wallet'));
		return view('tokens.token')->with(compact('token','html','country','adminMessage','wallet'));   
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
		try {
            $token = Token::where('symbol', $id )->firstOrFail();
        } catch (ModelNotFoundException $exception) {
            abort(404);
        }
		return view('tokens.edit')->with(['token'=> $token]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
		//die(var_dump($_FILES));
		$user = auth()->user();
		try {
            $token = Token::where('symbol', $id )->firstOrFail();
        } catch (ModelNotFoundException $exception) {
           return response()->json(['status' => 'ERROR','message' => 'Invalid Entry. Cannot find Entry']);
        }
		if($user->cant('update', $token))
		return response()->json(['status' => 'ERROR','message' => 'Permission Denied.  You cannot update this entry']);
		$input = Input::all();
		$request->validate([
			'contract_address'  => 'required',
            'contract_ABI_array'  => 'required',
            'ico_start'  => 'nullable|date',
            'ico_ends' => 'nullable|date',
            'token_price'    => 'nullable|numeric',
			'symbol'    => 'max:8|required',
			'website'    => 'nullable|max:100',
			'twitter'    => 'nullable|max:100',
			'facebook'    => 'nullable|max:100',
			'features'    => 'nullable|max:1000',
			'password'    => 'required'
			]);
		$token->fill($input);
		$img = $this->upload('image',542,374);
		if($img){
			$token->technology = $img;
		}
		$logo = $this->upload('logo');
		if($logo){
			$token->logo = $logo;
		}
		$token->save();
		$message ='<p>Token Saved Successfully!</p>';
		$required = 0;
  		$sale = "";
		$wallet = "";
		if (isset($request->showbuybuttonprice) ){
			$required += setting('showbuybuttonprice','0');
			$sale = 'Token Sale';
		}
		if (isset($request->enablesitewidewallet) ){
			$required += setting('enablesitewidewallet','0');
			$wallet = 'Sitewide Wallet';
		}
		if($required > 0){
			$adminRole = Role::where('slug','admin')->firstOrFail();
			$admin = $adminRole->users()->firstOrFail();
			if($user->account->balance <= $required){
				return response()->json(['status' => 'ERROR','message' => $message.'<p>Balance ('.$user->account->balance .')is too low to enable addons ('.$wallet.' & '.$sale.')</p>']);
			}
			try{
				$this->send($required,$admin->account->account, $user->account, $request->password,NULL);
			}catch(Exception $e){
				$message.="<p>Payment of {$required} ETH Failed with Error: ".$e->getMessage()."</p>";
				return response()->json(['status' => 'ERROR','message' => $message ]);
			}catch(\Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $e){
				$message .= $e->getMessage();
				$message.=': INVALID PASSWORD';
				return response()->json(['status' => 'ERROR','message' => 'Sent Tx Failed , '.$message]);
			}
			$message.="<p>Payment of {$required} ETH Sent successfully</p>";
		}
		
		if(!empty($sale)){
			$token->sale_active = 1;
		}
		if(!empty($wallet)){
			$token->wallet_active = 1;
		}
		$token->save();
       	try{
			list($token->name,$token->symbol,$token->decimals) = $this->getERC20CoinInfo($token);
		}catch(Exception $e){
			$message.="<p>Couldnt Verify Token details: ".$e->getMessage()."</p>";
			return response()->json(['status' => 'ERROR','message' => $message.'<p>'.$e->getMessage().'</p>']);
		}
		$token->slug = str_slug($token->name);
        $token->save();
		return response()->json(['status' => 'SUCCESS','message' => $message.'<p>'.$token->name.' updated successfully</p>']);
	
	
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
		$user = auth()->user();
		try {
            $token = Token::findOrFail($id);
        } catch (ModelNotFoundException $exception) {
			return response()->json(['status' => 'ERROR','message' => 'Cannot Find Token']);
        }
		if($user->cant('delete', $token)&&!$user->allowed('delete.tokens', $token))
		return response()->json(['status' => 'ERROR','message' => 'You cannot delete this Token. This is not your entry']);
		$request->validate(['checkConfirmDelete' => 'required']);
		// Soft Delete Token
        $token->delete();
		return response()->json(['status' => 'SUCCESS','message' => 'Deleted successfully']);
    }
	
	
	################# NONE RESOURESE
	################################
	#####################
	
	
	
	
	/*
	*Fast way to internally resolve  contract functions esp rate and totalSupply
	*/
	
	
	
	
	public function queryContract(Request $request){
		$request->validate([
			'contract'  => 'required',
			'token_id'  => 'required|numeric',
            'func'  => 'required',
		]);
		$token = Token::findOrFail($request->token_id);
		$func = $request->func;
		$construct = isset($request->construct)?$request->construct:[];
		$contract = $request->contract;
		$password = !empty($request->password)?$request->password:NULL;
		$eth = isset($request->eth)&&!empty($request->eth)?$request->eth:0;
		//$result = $this->resolve($contract, $token, $func, $construct, $password, $eth );
		try{
			$result = $this->resolve($contract, $token, $func, $construct, $password, $eth );
		}catch(\Exception $e){
			return response()->json(['status' => 'ERROR','message' => $e->getMessage()]);
		}
		return response()->json(['status' => 'SUCCESS','message' =>  $result]);
	}
	
	
	
	
	
	
	 public function icoindex()
    {
		
		$tokens = Token::latest('change_pct')->whereIn('symbol', explode('|', setting('dashBoardTokens')))->take(6)->get();
        $table =route('coins.ico.table','all');
        return view('tokens.icos', compact('table','tokens'))->with(['title'=>'ICO <small> <a href="'.route('myicos').'" >  Show Mine</a></small>']);
    }
	
	public function mytokens()
    {
		
		$tokens = Token::latest('change_pct')->whereIn('symbol', explode('|', setting('dashBoardTokens')))->take(6)->get();
        $table = route('coins.table','mine');
        return view('tokens.list', compact('table', 'tokens'))->with(['title'=>'Market Capitilization <small><a href="'.route('coins.index').'" > Show All</a></small>']);
    }
	
	 public function myicos()
    {
		$tokens = Token::latest('change_pct')->whereIn('symbol', explode('|', setting('dashBoardTokens')))->take(6)->get();
        $table =route('coins.ico.table','mine');
        return view('tokens.icos', compact('table','tokens'))->with(['title'=>'ICO <small> <a href="'.route('coins.ico').'" >  Show All</a></small>']);
    }
	
	
	public function table($list)
    {
	  $table = Datatables::of(Token::query());
	  if($list === 'mine'){
		  $table = Datatables::of(auth()->user()->coins()->get());
	  }
	  $country = Country::site()->firstORFail();	
      return $table
	  		->escapeColumns([])
			->editColumn('logo', function($token){
				return '<a href="'.route('coins.show',$token->symbol).'">
							<img  src="'.route('coins.image',$token->logo).'" class="img-circle token_logo" alt="'.$token->name.'" title="'.$token->name.'"/>
						</a>';
			})
			
			->editColumn('price', function($token)use($country){
				
				return $country->symbol.number_format($token->price,$country->decimal_digits);
			})
			->editColumn('volume', function($token)use($country){
				return number_format($token->volume,$country->decimal_digits);
			})
			->editColumn('market_cap', function($token)use($country){
				return number_format($token->market_cap,$country->decimal_digits);
			})
			->editColumn('change_pct', function($token){
				return $token->change_pct > 0?'<span class="green">'.$token->change_pct.'</span>':'<span class="red">'.$token->change_pct.'</span>';
			})
			
			
			->editColumn('name', function($token){
				$name ='<span>
							<a data-placement="right" data-toggle="tooltip" title="View Details" class="green" href="'.route('coins.show',$token->symbol).'">'.substr($token->name,0,15).'</a>
						</span>';
				if(auth()->check()){
					$user = auth()->user();
					if($token->wallet_active==1){
						$wallet = $user->wallets()->ofToken($token->id)->first();
						$bal = 'Wallet';
						$link = route('wallets.add');
						$link_class = 'ajax_link';
						$badge_class = 'badge-danger';
						if($wallet){
							$bal = $wallet->balance;
							$link = route('public.home');
							$link_class = '';
							$badge_class = 'badge-light';
						}
						$name.='&nbsp;&nbsp;<a data-placement="right" data-toggle="tooltip" title="Create a Wallet for '.$token->name.' " type="button" data-token_id="'.$token->id.'" href="'.$link.'" class="'.$link_class.' btn-sm btn btn-primary tooltips">
							<span class="badge '.$badge_class.'">'.$bal.'</span>
							</a>';
					}
					if($token->user_id == $user->id)	{
							$name.='&nbsp;&nbsp;<a data-placement="right" data-toggle="tooltip" title="Edit Token" type="button"  href="'.route('coins.edit', $token->symbol).'" class="btn-sm btn btn-danger tooltips"><i class="fa fa-edit"></i> Edit</a>';
					}
				}
			
				return $name;
			})
			->toJson();
	    
    }
	
	
	public function icotable($list)
    {	  
	  $country = Country::site()->firstORFail();	
	  $table = Datatables::of(Token::byType('ico'));
	  if($list === 'mine'){

		  $table = Datatables::of(auth()->user()->coins()->byType('ico')->get());
	  }
      return $table
	  		->escapeColumns([])
			->editColumn('logo', function($token){
				return '<a href="'.route('coins.show',$token->symbol).'">
							<img src="'.route('coins.image',$token->logo).'" class="img-circle token_logo" alt="'.$token->name.'" title="'.$token->name.'"/>
						</a>';
			})
			
			->editColumn('price', function($token)use($country){
				$price = $token->token_price.'/1ETH';
				if($token->sale_active && $token->isLive)
				$price.='&nbsp;<a data-placement="right" data-toggle="tooltip" title="Join The ICO" class="btn btn-sm btn-danger" href="'.route('coins.show',$token->symbol).'"><i class="fa fa-shopping-cart"></i> BUY NOW</a>';
				return $price;
				
			})
			
			->editColumn('change_pct', function($token){
				return $token->change_pct > 0?'<span class="green">'.$token->change_pct.'</span>':'<span class="red">'.$token->change_pct.'%</span>';
			})
			
			->editColumn('name', function($token){
				
				$name ='<span>
							<a data-placement="right" data-toggle="tooltip" title="View Details" class="green" href="'.route('coins.show',$token->symbol).'">'.substr($token->name,0,15).'</a>
						</span>';
				if(auth()->check()){
					$user = auth()->user();
					if( $token->wallet_active==1){
						$wallet = $user->wallets()->ofToken($token->id)->first();
						$bal = 'Wallet';
						$link = route('wallets.add');
						$link_class = 'ajax_link';
						$badge_class = 'badge-danger';
						$title = 'Add to My Wallets';
						if($wallet){
							$title = 'Wallet Balance '.$token->symbol.$wallet->balance;
							$bal = $wallet->balance;
							$link = route('public.home');
							$link_class = '';
							$badge_class = 'badge-light';
						}
						$name.='&nbsp;&nbsp;<a data-placement="right" data-toggle="tooltip" title="'.$title.'" type="button" data-token_id="'.$token->id.'" href="'.$link.'" class="'.$link_class.' btn-sm btn btn-primary tooltips">
							<span class="badge '.$badge_class.'">'.$bal.'</span>
							</a>';
						
						
					}
					if($token->user_id == $user->id)	{
							$name.='&nbsp;&nbsp;<a data-placement="right" data-toggle="tooltip" title=" Edit ICO " type="button"  href="'.route('coins.edit', $token->symbol).'" class="btn-sm btn btn-danger tooltips"><i class="fa fa-edit"></i> Edit</a>';
					}
				}
				return $name;
			})
			->toJson();
	    
    
	}

	
	
	/**
     * /token/deploy.
     *
     * 
     *
     * @return mixed
     */
    public function deployToken()
    {
		if(setting('enableDeploy','no')!='yes')
		return response()->view('errors.404', [], 500);
		$contracts = Contract::all();
		return view('tokens.deploy')->with(compact('contracts'));
    }
	
	
	
	/**
     * deploy a Token Smart Contract.
     *
     * @param $name
     *
     * @throws Laracasts\Validation\FormValidationException
     *
     * @return mixed
     */
    public function storeDeploy(Request $request)
    {
		if(setting('enableDeploy','no')!='yes')
		return response()->json(['status' => 'ERROR','message' => "404 NOT FOUND"]);
		$user = auth()->user();
        $token = new Token();
		$token->user_id = $user->id;
        $input = Input::all();
		$request->validate([
			'contract'  => 'required',
            'ico_start'  => 'nullable|date',
            'ico_ends' => 'nullable|date',
			'website'    => 'nullable|max:100',
			'twitter'    => 'nullable|max:100',
			'facebook'    => 'nullable|max:100',
			'features'    => 'nullable|max:1000',
			'password'    => 'required'
		]);
		$contract_id = $request->input('contract');
		$contract = Contract::findOrFail($contract_id);
		$constract = $construction = $request->input('contruction_'.$contract_id);
		if(isset($construction['_symbol']))
			$symbol = $construction['_symbol'];
		elseif(isset($construction['tokenSymbol']))
			$symbol = $construction['tokenSymbol'];
		elseif(isset($construction['_symbol_']))
			$symbol = $construction['_symbol_'];
		else
		return response()->json(['status' => 'ERROR','message' => "This Symbol is not set"]);
		$taken = Token::where('symbol',$symbol)->first();
		if($taken){
			return response()->json(['status' => 'ERROR','message' => "This Symbol aready Exists on Our system. Please Choose another Symbol"]);
		}
		$token->fill($input);
		$required = setting('tokencreationprice','0');
		$adminRole = Role::where('slug','admin')->firstOrFail();
		$admin = $adminRole->users()->firstOrFail();
		$list = "";
		$sale = "";
		$wallet = "";
		$message ="";
		
		$token->ico_address = $request->input('ico_address_'.$contract_id, NULL);
		if(!empty($token->ico_address))
		$token->ico_pass = $request->password;
		if(!empty($contract->buy_tokens_function)){
			if(empty($token->ico_address)){
				return response()->json(['status' => 'ERROR','message' => 'Please specify your your ICO address for this Contract . Its required']);
			}
			if(strtolower($token->ico_address) ==strtolower( auth()->user()->account->account)){
				return response()->json(['status' => 'ERROR','message' => 'This Deploying Account Cannot be used to accept Ether. This password will be available online!!']);
			}
		}
		
		if (isset($request->distributetokenprice) ){
			$required += setting('distributeTokenprice','0');
			$distribute = "<p>Payment for Sitewide Wallet Creation (".setting('distributeTokenprice','0')." ETH ) successfull</p>";
		}
		
		if (isset($request->icolistingprice) ){
			$token->ico_active = 1;
			$required += setting('icolistingprice','0');
			$list = "<p>Payment for ICO Listing (".setting('icolistingprice','0')." ETH ) successfull</p>";
		}
		  
		if (isset($request->showbuybuttonprice) ){
			$token->sale_active = 1;
			$required += setting('showbuybuttonprice','0');
			$sale = "<p>Payment for Enable Sales (".setting('showbuybuttonprice','0')." ETH )successfull</p>";
		}
		
		if (isset($request->enablesitewidewallet) ){
			$token->wallet_active = 1;
			$required += setting('enablesitewidewallet','0');
			$wallet = "<p>Payment for Enable Wallet (".setting('enablesitewidewallet','0')." ETH ) successfull</p>";
		}
		
		if($required > 0){
			$total =$user->account->balance + 0.000047;
			if($total <= $required){
				return response()->json(['status' => 'ERROR','message' => 'Balance is too low to deploy Token. Your need '.$total.'ETH to perform this action']);
			}
			try{
				$this->send($required,$admin->account->account, $user->account, $request->password, NULL);
				$message .= "<p>Payment for Deployment (".setting('tokencreationprice','0')." ETH ) Successfull</p>";
			}catch(\Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $e){
				$message = $e->getMessage();
				$message.=': INVALID PASSWORD';
				return response()->json(['status' => 'ERROR','message' => 'Sent Tx Failed , '.$message]);
			}catch(\Exception $e){
				return response()->json(['status' => 'ERROR','message' => 'Sent Tx Failed , '.$e->getMessage()]);
			}
		}
		$message.= $sale.$wallet.$list ;
		$contract = Contract::findOrFail($contract_id);
		$contractABI = $tokenABI = $contract->abi ;
		$contractBIN = $contract->bin ;
		
		$token->contract_id = !empty($contract)?$contract->id:NULL;
		if($request->input('type')== 'custom'){
			$contractABI = $tokenABI = $request->input('contract_ABI_array');
			$contractBIN = $request->input('contract_BIN');
		}
		$construction = empty($construction)?[]:$this->convertIntegers($construction);
		if($request->input('type')=='clone'){
			$token = Token::findOrFail($request->input('token_id'));
			$contractABI = $tokenABI = $token->contract_ABI_array ;
			$contractBIN = $token->contract_BIN ;
		}
		if(!empty($contract->mainsale_abi)){
			$contractABI = $contract->mainsale_abi;
			$tokenABI = $contract->abi;
		}
		try{
			$token->supply = 'txhash_'.$this->deploy($user->account, $request->password, $contractABI,$contractBIN,$construction);
		}catch(\Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $e){
			return response()->json(['status' => 'ERROR','message' => $e->getMessage().'. Wrong Wallet Password']);
		}catch(Exception $e){
			return response()->json(['status' => 'ERROR','message' => $e->getMessage()]);
		}
		$token->token_price = isset($construct['_rate'])?$construct ['_rate']:$request->input('rate_'.$contract_id);
		$token->price = !empty($token->token_price)?1/$token->token_price:1;
		$token->contract_ABI_array = $tokenABI; 
		$token->contract_BIN = $contractBIN;
		$token->account_id = $user->account->id;
		$token->active = 3;
		$token->net = setting('ETHEREUMNETWORK');
		$token->contract_address = md5(time());
		$token->name = str_random(10); 
		$token->symbol = str_random(5);
		$token->slug = $token->symbol;  
		$img = $this->upload('image',542,374);
		if($img){
			$token->technology = $img;
		}
		
		$logo = $this->upload('logo');
		if($logo){
			$token->logo = $logo;
		}
		$token->save();
		return response()->json(['status' => 'SUCCESS','message' =>$message.' Token Deployed Succesfully. Your Token Address will be available after confirmation at the blockchain.']);
    }

 
   

   
    /**
     * Upload Token Images
     *
     * @param $file 542/374
     *
     * @return mixed
     */
    public function upload($image = 'file', $w = 300, $h=300)
    { 
        if (Input::hasFile($image)) {
            $currentUser = auth()->user();
            $avatar = Input::file($image);
            $filename = md5(time()).'.'.$avatar->getClientOriginalExtension();
            $save_path = storage_path().'/images/';
            $path = $save_path.$filename;
            // Make the user a folder and set permissions
            File::makeDirectory($save_path, $mode = 0755, true, true);
            // Save the file to the server
            Image::make($avatar)->fit($w, $h)->save($save_path.$filename);
			return $filename ;
        } else {
            return false;
        }
    }
	
    /**
    /**
    * Method : DELETE
    *
    * @return delete images
    */
    public function deleteImage($image) {
        $file = storage_path().'/images/'.$image;
		if(is_file($file)){
			@unlink($file);
		}
        return true;  
    }

    /**
     * Show token Image.
     *
     * 
     * @param $image
     *
     * @return string
     */
    public function getTokenImage($image)
    {
		$image = explode('@',$image);
		$img = Image::make(storage_path().'/images/'.$image[0]);
		if(isset($image[1])&&!empty($image[1])){
			list($w,$h) = explode('x',strtolower($image[1]));
			$img->fit($w, $h);
		}
        return $img->response();
    }
	
	
    /**
     * Show token Image.
     *
     * 
     * @param $image
     *
     * @return string
     */
    public function downloadContract($id)
    {
		$token = Token::findOrFail($id);
		$user = auth()->user();
		if(!$token->account()->count()||$user->account->id != $token->account->id )
		return response()->json(['status' => 'ERROR','message' => 'You dont Have access to this contract.']);
		return response()->json([
			'status' => 'SUCCESS',
			'message' => 'Your Download has started',
			'file'=> $token->contract->contract,
			'filename'=> $token->contract->name .' Solidity Contract'
		]);
		
    }


}
