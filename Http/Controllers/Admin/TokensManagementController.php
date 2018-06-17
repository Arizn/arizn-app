<?php

namespace App\Http\Controllers\Admin;

use App\Models\Profile;
use App\Models\Token;
use App\Models\Transaction;
use App\Models\Order;
use Auth;
use Illuminate\Http\Request;
use jeremykenedy\LaravelRoles\Models\Role;
use Validator;
use Illuminate\Support\Facades\Form;
use Illuminate\Support\Facades\URL;
use Yajra\Datatables\Datatables;
use File;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Session;
use Image;

class TokensManagementController extends \App\Http\Controllers\Controller
{
	/**
	 * Create a new controller instance.
	 *
	 * @return void
	 */
	public function __construct(){
		$this->middleware('auth');
	}
	
	
	
	
	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index(){
		return View('_admin.tokens.list');
	}
	
	
	
	
	
	/**
	 * Show the form for creating a new resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	
	public function create(){
		return view('_admin.tokens.create');
	}
	
	/**
	 * Store a newly created resource in storage.
	 *
	 * @param \Illuminate\Http\Request $request
	 *
	 * @return \Illuminate\Http\Response*/
	
	public function store(Request $request){
		$request->validate([
			'name'         => 'required|unique:tokens',
			'contract_ABI_array'  => 'required',
			'contract_address'    => 'required',
			'symbol' => 'required|max:8|unique:tokens',
			'decimals' => 'required',
			'twitter' => 'max:100',
			'facebook' => 'max:100',
		
		]);
		$ico_start = $request->input('ico_start',NULL);
		$ico_start = $ico_start? \Carbon\Carbon::parse($ico_start):NULL;
		$ico_ends = $request->input('ico_ends',NULL);
		$ico_ends = $ico_ends? \Carbon\Carbon::parse($ico_ends):NULL;
		$tkn = [
			'name'  => $request->input('name'),
			'logo'  => $request->input('logo', '/public/logo/'.$request->input('name','logo').'png'),
			'contract_address'      => $request->input('contract_address'),
			'contract_ABI_array'    => $request->input('contract_ABI_array'),
			'contract_Bin'          => $request->input('contract_Bin',NULL),
			'symbol'                => $request->input('symbol'),
			'slug'                  => $request->input('symbol'),
			'description'			=> $request->input('description'),
			'user_id'             	=> auth()->user()->id,
			'ico_start'             => $ico_ends,
			'ico_ends'              => $ico_ends,
			'token_price'           => $request->input('token_price',NULL),
			'price'         		    => $request->input('token_price', 1),
			'decimals'         		=> $request->input('decimals', 18),
			'website' 				=> $request->input('website',NULL),
			'twitter' 				=> $request->input('twitter',NULL),
			'facebook'			 	=> $request->input('facebook',NULL),
			'features' 				=> $request->input('features',NULL),
		];
		$token = Token::create($tkn);
		$img = $this->upload('image');
		if($img){
			$token->technology = $img;
		}
		$logo = $this->upload('logo');
		if($logo){
			$token->logo = $logo;
		}
		$token->save();
		return response()->json(['status' => 'SUCCESS','message' => 'Token Created Successfully']);
	
	}
	
	/**
	 * Display the specified resource.
	 *
	 * @param int $id
	 *
	 * @return \Illuminate\Http\Response*/
	
	public function show($id){
		$token = Token::find($id);
		return view('_admin.tokens.show')->with(['token'=>$token]);
	}
	
	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param int $id
	 *
	 * @return \Illuminate\Http\Response*/
	 
	public function edit($id){
		$token = Token::findOrFail($id);
		return view('_admin.tokens.edit')->with(['token'=>$token]);
	}
	
	/**
	 * Update the specified resource in storage.
	 *
	 * @param \Illuminate\Http\Request $request
	 * @param int                      $id
	 *
	 * @return \Illuminate\Http\Response*/
	
	public function update(Request $request, $id){
		$token = Token::find($id);
		$request->validate([
			'name'         => 'required',
			'contract_ABI_array'  => 'required',
			'contract_address'    => 'required',
			'symbol' => 'required|max:8',
			'decimals' => 'required',
			'description' => 'required',
			'facebook' => 'max:100',
		]);
		$ico_start = $request->input('ico_start',NULL);
		$ico_start = $ico_start? \Carbon\Carbon::parse($ico_start):NULL;
		$ico_ends = $request->input('ico_ends',NULL);
		$ico_ends = $ico_ends? \Carbon\Carbon::parse($ico_ends):NULL;
		$token->name  = $request->input('name');
		$token->contract_address = $request->input('contract_address');
		$token->contract_ABI_array =  $request->input('contract_ABI_array');
		$token->contract_Bin =  $request->input('contract_Bin');
		$token->symbol = $request->input('symbol');
		$token->description = $request->input('description');
		$token->ico_start =  $ico_ends;
		$token->ico_ends =  $ico_ends;
		$token->token_price = $request->input('token_price',NULL);
		$token->price = $request->input('token_price', 1);
		$token->decimals = $request->input('decimals', 18);
		$token->website = $request->input('website');
		$token->twitter = $request->input('twitter');
		$token->facebook =  $request->input('facebook');
		$token->features = $request->input('features');
		$img = $this->upload('image');
		if($img){
			$token->technology = $img;
		}
		$logo = $this->upload('logo');
		if($logo){
			$token->logo = $logo;
		}
		$token->save();
		return response()->json(['status' => 'SUCCESS','message' => 'Token Updated Successfully']);
	}
	
	/**
	 * Remove the specified resource from storage.
	 *
	 * @param int $id
	 *
	 * @return \Illuminate\Http\Response
	 */
	
	public function destroy($id){
		$token = Token::findOrFail($id);
		$token->delete();
		return response()->json(['status' => 'SUCCESS','message' => 'Token Deleted Successfully']);
	
	}
	
	public function table(){
		$token = Token::where('symbol','!=','ETH')->get();
		return Datatables::of($token)
			->escapeColumns([])
			->addColumn('ico_status', function ($token) {
				$name = 'NONE';
				$label = 'danger';
				if($token->ico_active == 1){
					if(\Carbon\Carbon::now()->lessThan(new \Carbon\Carbon($token->ico_start) )){
						$name = $token->ico_start;
						$label = 'warning';
					}
					if(\Carbon\Carbon::now()->greaterThan(new \Carbon\Carbon($token->ico_end) )){
						$name = 'Complete';
						$label = 'info';
					}
					if(\Carbon\Carbon::now()->lessThan(new \Carbon\Carbon($token->ico_end))&&\Carbon\Carbon::now()->greaterThan(new \Carbon\Carbon($token->ico_start) )){
						$name = 'UNDERWAY';
						$label = 'info';
					}
				}
				return '<a data-table="tokens"  class="ajax_link refresh btn btn-sm btn-'.$label.' btn-block" href="'.\URL::to('admin/tokens/toggle-ico-status/' . $token->id).'" data-toggle="tooltip" title="Edit">
							<span class="hidden-xs hidden-sm hidden-md">'.$name.'</span>
						 </a>';
	
			})->addColumn('wallet_status', function ($token) {
				$name = 'INACTIVE';
				$label = 'danger';
				if($token->wallet_active == 1){
					$name = 'ACTIVE';
					$label = 'success';
				}
				return '<a data-table="tokens"  class="ajax_link refresh btn btn-sm btn-'.$label.' btn-block" href="'.\URL::to('admin/tokens/toggle-wallet-status/' . $token->id).'" data-toggle="tooltip" title="Edit">
							<span class="hidden-xs hidden-sm hidden-md">'.$name.'</span>
						 </a>';
	
			})->editColumn('sale_active', function ($token) {
				$name = 'NO';
				$label = 'danger';
				if($token->sale_active == 1){
					$name = 'YES';
					$label = 'success';
				}
				return '<a data-table="tokens"  class="ajax_link refresh btn btn-sm btn-'.$label.' btn-block" href="'.route('admin.tokens.togglefeaturedstatus', $token->id).'" data-toggle="tooltip" title="Edit">
							<span class="hidden-xs hidden-sm hidden-md">'.$name.'</span>
						 </a>';
	
			})->addColumn('status', function ($token) {
				$name = 'OFF';
				$label = 'danger';
				if($token->active == 1){
					$name = 'ON';
					$label = 'success';
				}
				return '<a data-table="tokens" class="ajax_link refresh btn btn-sm btn-'.$label.' btn-block" href="'.\URL::to('admin/tokens/toggle-status/' . $token->id).'" data-toggle="tooltip" title="Edit">
							<span class="hidden-xs hidden-sm hidden-md">'.$name.'</span>
						 </a>';
	
			})->addColumn('user', function ($token) {
				if($token->user()->count())
				return  $token->user->name;
				return '----';
			})->editColumn('actions', function($token){
				return '<a class="btn btn-success btn-sm" href="'.route('admin.tokens.edit',$token->id).'"><i class="fa fa-edit"></i> Edit</a>'.
					   '&nbsp;&nbsp;<a table="tokens" data-_method="DELETE" data-confirm="Do you really want to delete this ?" class="btn btn-danger btn-sm ajax_link confirm" data-_method="DELETE" href="'.route('admin.tokens.destroy',$token->id).'"><i class="fa fa-times"></i>DELETE</a>';
			}) ->toJson();
	}
	
	// toogles
	public function toggleActivate($id){
		$token = Token::findOrFail($id);
		if($token->active == 3){
			return response()->json(['status' => 'SUCCESS','message' => 'Process Failed. This Token has not been confirmed on the blockchain Yet.']);
		}
		$token->active = $token->active==1?0:1;
		$token->save();
		$action= $token->active==1?'Activated':'Deactivated';
		return response()->json(['status' => 'SUCCESS','message' => 'Token '.$action.' Successfully']);
	}
	
	
	public function toggleWallet($id)
	{
		$token = Token::findOrFail($id);
		$token->wallet_active = $token->wallet_active==1?0:1;
		$token->save();
		$action= $token->wallet_active==1?'Activated':'Deactivated';
		return response()->json(['status' => 'SUCCESS','message' => 'Token Wallet '.$action.' Successfully']);
	}
	
	public function toggleFeatured($id)
	{
		$token = Token::findOrFail($id);
		$token->sale_active = $token->sale_active==1?0:1;
		$token->save();
		$action= $token->sale_active==1?'Featured  Sale Activated':'Featured  Sale Deactivated';
		return response()->json(['status' => 'SUCCESS','message' => 'Token '.$action.' Successfully']);
	}
	
	
	public function toggleIco($id){
		$token = Token::findOrFail($id);
		$token->ico_active =  $token->ico_active==1?0:1;
		$token->save();
		$action= $token->ico_active==1?'Activated':'Deactivated';
		return response()->json(['status' => 'SUCCESS','message' => 'Token Initial Coin Offering '.$action.' Successfully']);
	}
	
	 private function upload($image = 'file')
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
            Image::make($avatar)->resize(300, 300)->save($save_path.$filename);
			return $filename ;
        } else {
            return false;
        }
    }
	
	
	
	
	
}
