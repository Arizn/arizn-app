<?php

namespace App\Http\Controllers;

use Auth;
use \App\Models\Token;
use \App\Models\Wallet;
use \App\Models\User;
use Yajra\Datatables\Datatables;
use Illuminate\Http\Request;
use App\Traits\WalletTrait;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
	use WalletTrait;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
		$user = Auth::user();
		$siteToken = Token::symbol(setting('siteToken','ETH'))->first();
		$ethToken = Token::symbol('ETH')->first();
		$tokenWallet = NULL;
		if($siteToken->symbol != 'ETH'){ 
			$tokenWallet = $user->wallets()->ofToken($siteToken->id)->firstOrNew([
				'user_id'=>$user->id,
				'account_id'=>$user->account->id,
				'token_id'=>$siteToken->id,
			]);
			$tokenWallet->save();
		}
		$TokensList = Token::where('wallet_active',1)->whereNotIn('id',$user->wallets()->pluck('token_id'))->get()->pluck('name','id');
		$icoToken = NULL;
		if(setting('APP_MODE','ICO')=='ICO'){
			$icoToken = Token::symbol(setting('siteToken','ETH'))->LiveNow()->first();
		}
		$token  =  is_null($icoToken)?$siteToken:$icoToken;
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
						}
						$place = isset($placeHolders[$input->name])?$placeHolders[$input->name]:"Required";
						if($input->type == 'bool'){
							$place = "use 1 for true or 0 for false";
						}
						
						$t = '<div class="item form-group">
									<label for="'.$input->name.'">'.$abi->payable.str_replace('_',' ', ucfirst($input->name)).'</label>
									<input placeholder="'.$place.'" type="text" id="'.$input->name.'" name="construct['.$input->name.']" style="min-width:200px" class="form-control'.$class.'">
									
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
		$tokens = Token::where('active', 1)->latest('change_pct')->whereIn('symbol', explode('|', setting('dashBoardTokens')))->take(6)->get();
		$tkn = $token;
		$featured = Token::where('sale_active', 1)->where('active', 1)->latest('updated_at')->take(6)->get();
		$view = 'pages.user.home';
		if(setting('APP_MODE','ICO')=='ICO')
		$view = 'pages.user.ico';
		$bit = Token::where('family','bitFamily')->get()->pluck('id')->all();
		$bitwallets = $user->wallets()->whereIn('token_id',$bit)->get();
	
		//
        return view($view, compact('user','tokens','html','tkn','ethToken','icoToken','featured','TokensList','siteToken','tokenWallet','token','adminMessage','bitwallets'));
    }
	
	 public function txTable()
    {
		
		$tx = auth()->user()->transactions()->with('token')->get()->makeVisible('created_at');
        return Datatables::of($tx)
		    ->escapeColumns([])
			->addColumn('symbol', function ($tx) {
				return  $tx->token->symbol;
			})
			->editColumn('amount', function ($tx) {
				return  $tx->amount.' '.$tx->token->symbol;
			})
			->editColumn('tx_hash', function ($tx) {
				if($tx->token->family =='bitFamily')
				$link = $this->coin_tx_link($tx->tx_hash, $tx->token->symbol);
				else
				$link = $this->tx_link($tx->tx_hash); 
				
				return ' <a target="_blank"  href="'.$link.'" data-toggle="tooltip" class="tooltips" data-original-title="Explore at the Blockchain" title="Explore at the Blockchain">'.substr($tx->tx_hash,0,12).'.....
                         </a>';
			})
			->editColumn('from_address', function ($tx){
				if($tx->token->family =='bitFamily')
				$link = $this->coin_address_link($tx->from_address, $tx->token->symbol);
				else
				$link = $this->address_link($tx->from_address); 
				return $tx->type=="query"?'<span style="cursor:pointer" data-toggle="tooltip" class="tooltips" data-original-title="'.$tx->description.'" title="'.$tx->description.'">'.str_limit($tx->description,20).'</span>':' <a target="_blank"  href="'.$link.'" data-toggle="tooltip" class="tooltips" data-original-title="View at Etherscan" title="Explore at the Blockchain">'.substr($tx->from_address,0,10).'.....
                         </a>';
			})
			->editColumn('to_address', function ($tx){
				if($tx->token->family =='bitFamily')
				$link = $this->coin_address_link($tx->from_address, $tx->token->symbol);
				else
				$link = $this->address_link($tx->from_address); 
				return ' <a target="_blank"  href="'.$link.'" data-toggle="tooltip" class="tooltips" data-original-title="View at Etherscan" title="View at Etherscan">'.substr($tx->to_address,0,10).'.....
                         </a>';
			})->toJson();
    }
	
	 public function walletsTable()
    {
		$wallets = auth()->user()->wallets()->has('token')->get();
        return Datatables::of($wallets)
		    ->escapeColumns([])
			->setRowClass(function ($wallet) {
				return $wallet->token->isICO ? 'info' : '';
			})
			->addColumn('symbol', function ($wallet) {
				$ico = $wallet->token->isICO?' (ICO)':'';
				return  '<a style="color:green" href="'.route("coins.show",$wallet->token->symbol).'">'.$wallet->token->symbol.$ico.'</a>'; 
			})
			->editColumn('usd_balance', function ($wallet) {
				$cur = $wallet->token->isICO?'ETH':setting("sitecurrency","USD");
				return  '<span class="'.$wallet->token->symbol.'_usd_balance">'.number_format($wallet->token->price*$wallet->balance,2).'</span>'.$cur;
			})
			
			->editColumn('balance', function ($wallet) {
				return $wallet->balance==NULL?"0.0000":$wallet->balance ;
			})
			->editColumn('action', function ($wallet) {
				return ' <a class="ajax_link authorize confirm" data-confirm="Are you sure you want to delete this wallet?" table ="walletsTable" data-table ="walletsTable" href="'.route("wallets.remove",$wallet->id).'"><i class="fa fa-close"></i></a>';
			})
			->toJson();
    }
	

	
	public function addWallet(Request $request){
		$request->validate([
			'token_id'=>'required|numeric',
			'password'=>'required'
		]);
		$token_id = $request->input('token_id');
		$password = $request->input('password');
		$user = auth()->user();
		$password = $request->input('password');
		if (!Hash::check($password, $user->password)) {
			return response()->json(['status' => 'ERROR','message' => 'Invalid Password. Please Input your Login Password. Your private Keys are encrypted']);
		}
		$token = \App\Models\Token::findOrFail($token_id);
		if($token->family == "bitFamily"){
			$coldKey = setting($token->symbol.'_coldKey',false);
			if(empty($coldKey)){
				return response()->json(['status' => 'ERROR','message' => 'Wallet SetUp Failed. Admin Multisig Setup Incomplete']);
			}
			try{
				$this->coin_create_wallet( $user->account , $password , $token);
			}catch(\Exception $e){
				return response()->json(['status' => 'ERROR','message' => $e->getMessage()]);
			}
			return response()->json(['status' => 'SUCCESS','message' => $token->name.' Wallet Initialized Successfully']);
		}
		$wallet = auth()->user()->wallets()->ofToken($token_id)->firstOrNew([
			'user_id'=>auth()->user()->id,
			'account_id'=>auth()->user()->account->id,
			'token_id'=>$token_id,
		]);
		$wallet->save();
		//$wallet->updateBalance();
		return response()->json(['status' => 'SUCCESS','message' =>  $token->name.' Wallet Added Successfully']);
	}
	
	/*Remove Wallet From the Database*/
	public function removeWallet(Request $request, $token_id){
		
		$wallet = Wallet::findOrFail($token_id);
		$message = $wallet->token->name;
		$wallet->forceDelete(); 
		return response()->json(['status' => 'SUCCESS','message' =>' Your '. $message.' Wallet Was Removed Successfully']);
	}
	
	
	public function showNotification(Request $request){
		try{
			$notification = $request->user()->notifications()->where('id', $request->id)->first();
            if($notification) {
                $notification->markAsRead();
            }
			return response()->json(['status' => 'SUCCESS','message' => $notification->data['message'] ]);
		}catch(Exception $e){
			return response()->error(['status' => 'ERROR','message' => $e->getMessage() ]);
		}
		
		
	}
	
	
	public function backupKey(Request $request){
		$user = auth()->user();
		$password = $request->input('password');
		if (Hash::check($password, $user->password)) {
			list($mnc,$Acc) = $this->unlocPrivateKey($user->account,$password);
			$resp ='###YOUR BACKUP DATA###'.PHP_EOL;
			$resp .='##MNEMONIC:'.PHP_EOL;
			$resp .=$mnc.PHP_EOL;
			$resp .='##PASSWORD:'.PHP_EOL;
			$resp .=$password.PHP_EOL;
			$resp .='##MASTER PRIVATE KEY:'.PHP_EOL;
			$resp .= $Acc->getMasterXpriv().PHP_EOL;
			$resp .='##ETHEREUM PUBLIC KEY at m/44\'/60\'/0\'/0:'.PHP_EOL;
			$resp .= $Acc->getXpub().PHP_EOL;
			return response()->json(['filename'=>$user->account->account,'file'=>$resp,'status' => 'SUCCESS','message' => 'Backup Generated successfully. Download Has started']);
		}
		return response()->json(['status' => 'ERROR','message' => 'Invalid Password. Please Check your password']);
		
		
	}
	
	public function sendToken(Request $request){
		$request->validate([
            'amount'=> 'required',
            'token_id'  => 'required',
            'to'  => 'required',
            'password' => 'required|min:3',
        ]);
		$user = auth()->user();
		$token = Token::findOrFail($request->input('token_id'));
		
		if($token->family == 'ethereum'&& strlen($request->to) !=42){
			
			
			return response()->json(['status' => 'ERROR','message' => 'Invalid Ethereum Address']);
		}
		
		if($token->family == 'bitFamily'){
			$wallet = $user->wallets()->ofToken($token->id)->first();
			
			try{
				$tx_hash =  $this->coin_send($request->input('amount'), $request->input('to'), $wallet, $request->input('password'), NULL , "high") 	;
			}catch(\Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $e){
				$message = $e->getMessage();
				$message.=': INVALID PASSWORD';
				return response()->json(['status' => 'ERROR','message' => 'Sent Tx Failed , '.$message]);
			}catch(\Exception $e){
				return response()->json(['status' => 'ERROR','message' => 'Sent Tx Failed , '.$e->getMessage()]);
			}
			return response()->json(['status' => 'SUCCESS','message' => 'Sent Tx Hash:<a target="_blank"  href="'.$this->coin_tx_link($tx_hash, $token).'">'.$tx_hash .'</a>']);
		}elseif($token->family == 'ethereum'){
			$gasLimit = empty($request->input('gasLimit'))?NULL:$request->input('gasLimit');
			$gasPrice = empty($request->input('gasPrice'))?NULL:$request->input('gasPrice');
			$account = $user->account;
			try{
				$tx_hash = $this->send(
					$request->input('amount'), 
					$request->input('to'), 
					$account, 
					$request->input('password'), 
					$token->symbol =='ETH'?NULL:$token->symbol,
					$gasLimit,
					$gasPrice
				) ;
			}catch(\Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $e){
				$message = $e->getMessage();
				$message.=': INVALID PASSWORD';
				return response()->json(['status' => 'ERROR','message' => 'Sent Tx Failed , '.$message]);
			}catch(\Exception $e){
				return response()->json(['status' => 'ERROR','message' => 'Sent Tx Failed , '.$e->getMessage()]);
			}
			return response()->json(['status' => 'SUCCESS','message' => 'Sent Tx Hash:<a target="_blank"  href="'.$this->tx_link($tx_hash).'">'.$tx_hash .'</a>']);
		}
		
	}
	
	
	
	
	
	public function buyToken(Request $request){
		
		$request->validate([
            'amount'=> 'required',
            'token_id'  => 'required|numeric',
            'password' => 'required',
        ]);
		$token = NULL;
		$user = auth()->user();
		$token = Token::findOrFail($request->input('token_id'));
		if($token->user_id == $user->id){
			return response()->json(['status' => 'ERROR','message' => 'You cannot buy your Own Token!!']);
		}
		$gasLimit = empty($request->input('gasLimit'))?NULL:$request->input('gasLimit');
		$gasPrice = empty($request->input('gasPrice'))?NULL:$request->input('gasPrice');
		$account = auth()->user()->account;
		$amount = $request->input('amount');
		$tkns = $amount * $token->token_price;
		$buytype = false;
		if($token->isBuyable){ // send eth
			$address = $token->contract_address;
			if(!empty($token->mainsale_address))
			$address = $token->mainsale_address;
		}elseif($token->account()->count()&&!empty($token->contract->buy_tokens_function)){
			$address = $token->ico_address;
			if(empty($address))
			$address = $token->account->account;
			if(empty($token->ico_pass))
			return response()->json(['status' => 'ERROR','message' => 'Token Sale is Not possible NOW']);
			
			if($token->contract->buy_tokens_function == 'transfer'){
				try{
					$tokenWallet = $token->user->wallets()->ofToken($token->id)->firstOrFail();
				}catch(Exception $e){
					return response()->json(['status' => 'ERROR','message' => 'Token Wallet is Not initialized, Try again Later']);
				}
				
				if($tokenWallet->balance < $tkns )
				return response()->json(['status' => 'ERROR','message' => 'Token Sale is Not possible NOW, Buy amount '.$tkns.' Greater than Token stock ( '.$token->account->balance.' ). Contact Token admin']);
			}
		}
		
		// check contract token balance 
		
		
		// collect payment
		try{
		   $tx_hash = $this->send(
				$amount, 
				$address, 
				$account, 
				$request->input('password')
		 ) ;
		 
		}catch(\Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $e){
			$message = $e->getMessage();
			$message.=': INVALID PASSWORD';
			return response()->json(['status' => 'ERROR','message' => 'Sent Tx Failed , '.$message]);
		}catch(\Exception $e){
			return response()->json(['status' => 'ERROR','message' => 'Sent Tx Failed , '.$e->getMessage()]);
		}
		$sold = new\App\Models\Icosale;
		$sold->token_id=$token->id;
		$sold->account_id=$account->id;
		$sold->amount= $tkns;
		$sold->ether = $amount ;
		$sold->ether_txhash = $tx_hash;
		// send tokens fi not buyable contract
		if($token->isBuyable){
				$sold->save();
			 	return response()->json(['status' => 'SUCCESS','message' => 'You sent Payment to the Token Contract successfully']);
		 }else{
			if($token->contract->buy_tokens_function == 'transfer'){
				
				try{
				   $tx_hash = $this->send(
						$tkns, 
						$account->account, 
						$token->account, 
						$token->ico_pass, 
						$token
				 ) ;
				}catch(\Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $e){
					$message = $e->getMessage();
					$message.=': INVALID PASSWORD';
					return response()->json(['status' => 'ERROR','message' => 'Token Delivery Failed. Send Tx Failed , '.$message]);
				}catch(\Exception $e){
					return response()->json(['status' => 'ERROR','message' => 'Token Delivery Failed. Send Tx Failed , '.$e->getMessage()]);
				}
				$sold->token_txhash = $tx_hash;
				$sold->save();
				return response()->json(['status' => 'SUCCESS','message' => 'Token Purchase successfull. Transaction confirmation is underway']);
				
			}else{ 
				try{
					$result = $this->resolve($request->contract, $token, $token->contract->buy_tokens_function, $request->input('contruction'), $token->ico_pass, 0, $token->account );
					
				}catch(\Exception $e){
					return response()->json(['status' => 'ERROR','message' => $e->getMessage()]);
				}
				$sold->token_txhash = $result;
				$sold->save();
				return response()->json(['status' => 'SUCCESS','message' =>  $result]);
			}

		}
	}
	public function metamaskSale(Request $request){
		$request->validate([
            'amount'=> 'required',
            'token_id'  => 'required|numeric',
            'tx_hash' => 'required',
        ]);
		$token = NULL;
		$token = Token::findOrFail($request->input('token_id'));
		$account = auth()->user()->account;
		$amount = $request->input('amount');
		$tkns = $amount * $token->token_price;
		$buytype = false;
		$sold = new\App\Models\Icosale;
		$sold->token_id=$token->id;
		$sold->account_id=$account->id;
		$sold->amount= $tkns;
		$sold->ether= $amount ;
		$sold->ether_txhash = $request->input('tx_hash');
		// send tokens if not buyable contract
		//todo validate tx
		if($token->isBuyable){
				$sold->save();
			 	return response()->json(['status' => 'SUCCESS','message' => 'You sent Payment to the Token Contract successfully']);
		 }else{
			if($token->contract->buy_tokens_function == 'transfer'){
				try{
				   $tx_hash = $this->send(
						$tkns, 
						$account->account, 
						$token->account, 
						$token->ico_pass, 
						$token
				 ) ;
				}catch(\Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $e){
					$message = $e->getMessage();
					$message.=': INVALID PASSWORD';
					return response()->json(['status' => 'ERROR','message' => 'Token Delivery Failed. Send Tx Failed , '.$message]);
				}catch(\Exception $e){
					return response()->json(['status' => 'ERROR','message' => 'Token Delivery Failed. Send Tx Failed , '.$e->getMessage()]);
				}
				$sold->ether_txhash = $tx_hash;
				$sold->save();
				return response()->json(['status' => 'SUCCESS','message' => 'Token Purchase successfull. Transaction confirmation is underway']);
				
			}else{ 
				try{
					$result = $this->resolve($request->contract, $token, $token->contract->buy_tokens_function, $request->input('contruction'), $token->ico_pass, 0, $token->account );
					
				}catch(\Exception $e){
					return response()->json(['status' => 'ERROR','message' => $e->getMessage()]);
				}
				$sold->ether_txhash = $result;
				$sold->save();
				return response()->json(['status' => 'SUCCESS','message' =>  $result]);
			}

		}
	}
	
	
}
