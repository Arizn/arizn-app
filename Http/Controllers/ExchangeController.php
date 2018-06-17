<?php


namespace App\Http\Controllers;

use App\Models\Rate;
use App\Models\User;
use App\Models\Order;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;
use jeremykenedy\LaravelRoles\Models\Role;

class ExchangeController extends Controller
{
	
    use \App\Traits\OrderTrait;
	use \App\Traits\WalletTrait;
	 /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
		if(setting('membersOnlyExchange',1)==1)
        $this->middleware('auth');
    }
	
	
	
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
		if(setting('enableExchange','no')=='no')
		return response()->view('errors.404', [], 500);
		$rates = Rate::with(['src','dst'])->get();
		$dstgates =[];
		$srcgates =[];
		foreach($rates as $y => $rate){
			foreach($rate->dst->gateways as $k=> $gate){
				$r = '<span class="dests '.$rates->where('dst_gateway',$gate['name'])->where('tosym',$rate->dst->symbol)->map(function($item){
					return $item->src_gateway.$item->fromsym;
				})->implode(' ').'"><input data-description="'.$rate->dst->description.'" data-name="'.$gate['name'].' '.$rate->dst->symbol.'"  data-img="'.route('home.coins.image',$gate['logo']).'" data-gate="'.$gate['name'].$rate->dst->symbol.'"  data-symbol="'.$rate->dst->symbol.'" class="card" id="'.$k.$y.'-'.$rate->dst->id.'" value="'.$rate->dst->id.'" type="radio" required name="destination" >
														<label data-toggle="tooltip" title ="'.$gate['name'].' '.$rate->dst->symbol.'" class="gateway-cc tooltips img-'.$gate['name'].'" for="'.$k.$y.'-'.$rate->dst->id.'"></label></span>';
				$dstgates[$gate['name'].$rate->dst->symbol] = $r;
			}
			
			foreach($rate->src->gateways as $k=> $gate){
				$r = '<span class="srcs '.$rates->where('src_gateway',$gate['name'])->where('fromsym',$rate->src->symbol)->map(function($item){
					return $item->dst_gateway.$item->tosym;
				})->implode(' ').'"><input data-description="'.$rate->src->description.'" data-name="'.$gate['name'].' '.$rate->src->symbol.'"  data-img="'.route('home.coins.image',$gate['logo']).'" data-gate="'.$gate['name'].$rate->src->symbol.'"  data-symbol="'.$rate->src->symbol.'" class="card" id="'.$k.$y.'-'.$rate->src->id.'" value="'.$rate->src->id.'" type="radio" required name="source" >
														<label data-toggle="tooltip" title ="'.$gate['name'].' '.$rate->src->symbol.'" class="gateway-cc tooltips img-'.$gate['name'].'" for="'.$k.$y.'-'.$rate->src->id.'"></label></span>';
				$srcgates[$gate['name'].$rate->src->symbol] = $r;
			}
		}
		$view = auth()->check()?"exchange.index":"exchange.openexchange";
		return view($view, compact('rates','dstgates','srcgates'));
    }
	
	public function rates(){
		$rates = Rate::with(['src','dst'])->get();
		return response()->json($rates->groupBy('pair_id'));
	}

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function initiate(Request $request)
    {
		if(setting('enableExchange','no')=='no')
		return response()->json(['status' => 'ERROR','message' => '404 Not found']);
		$auth = auth()->check();
		if($auth)
		$user = auth()->user();
		$adminRole = Role::where('slug','admin')->firstOrFail();
		$admin = $adminRole->users()->firstOrFail();
		$account = $admin->account;
		$request->validate([
            'rate_id'  => 'required|numeric',
			'source'  => 'required|numeric',
			'destination'  => 'required|numeric',
			'src_amt'  => 'required|numeric',
			'dst_amt'  => 'required|numeric',
			'src_account'  => 'required',
			'dst_account'  => 'required',
        ]);
		$rate = Rate::findOrFail($request->rate_id);
		$token =  $rate->src;
		$data = $request->all();
		$amount = $request->src_amt;
		$fee = bcdiv( $rate->fees, 100 ,2);
		$fees = bcmul($amount,$fee, $token->decimals);
		$exp = setting('tx_validity',10);
		$data['expiry'] = $exp ;
		$order = new Order();
		if($token instanceof \App\Models\Token){
			if($token->family=='bitfamily'){
				list($order->idx,$order->address) = $this->deriveCoinAddress($account);
			}else{
				list($order->idx,$order->address) = $this->deriveAddress($account);
			}
		}
		
		$order->item = 'exchange'; 
		$order->gateway = $rate->src_gateway; 
		$order->reference = md5(time().str_random('100'));
		$order->item_url = route('exchange');
		$order->expires_at = \Carbon\Carbon::now()->addMinutes((int)$exp);
		$order->account_id = $account->id; // seller
		$order->user_id = $auth?$user->id:NULL; // user 
		$order->pair_id = $rate->pair_id;
		$order->type='collect';
		$order->token_type=$rate->src_type;
		$order->rate_id = $rate->id;
		$order->token_id = $token->id;
		$order->item_data = $data;
		$order->amount = $amount;
		$order->fees = $fees;
		$order->symbol = $token->symbol;
		$order->save();
		
		// user is merchant hence accoun id
		$u_token = $rate->dst;
		$rcp = bcmul($amount,$rate->rate);
		$u_order = new Order();
		$u_order->gateway = $rate->dst_gateway; 
		$u_order->counter_id = $order->id;
		$u_order->idx = NULL;
		$u_order->address = $request->dst_account;
		$u_order->item = 'exchange'; 
		$u_order->reference = md5(time().str_random('100'));;
		$u_order->item_url = route('exchange');
		$u_order->expires_at = NULL;
		$u_order->type='send';
		$u_order->token_type=$rate->dst_type;
		$u_order->account_id =$auth?$user->account->id:NULL; 
		$u_order->user_id = $admin->id;
		$u_order->rate_id = $rate->id;
		$u_order->token_id = $u_token->id;
		$u_order->item_data = $data;
		$u_order->amount = $rcp;
		$u_order->fees = 0;
		$u_order->symbol = $u_token->symbol;
		$u_order->save();
		return response()->json(['URL'=>route('order',$order->reference),'status' => 'SUCCESS','message' => 'Please Wait......']);
		
	}

    /**
     * show orders history.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
   
	public function table()
    {
		$user = auth()->user();
		$orders = Order::where('user_id',$user->id)->whereNull('counter_id')->where('item','exchange')->with('counter')->get();
		
	  	$table = Datatables::of($orders);
	 	
      	return $table
	  		->escapeColumns([])
			->editColumn('from', function($order){
				return $order->symbol;
			})
			->editColumn('fromamount', function($order){
				return $order->amount;
			})
			->editColumn('fromstatus', function($order){
				$expired =  \Carbon\Carbon::now()->greaterThan($order->expires_at);
				$status = $order->status =="UNPAID"&&$expired?'EXPIRED':$order->status;
				return $status;
			})
			->editColumn('to', function($order){
				return $order->counter->symbol;
			})
			->editColumn('toamount', function($order){
				$expired =  \Carbon\Carbon::now()->greaterThan($order->expires_at);
				$status = $order->status =="UNPAID"&&$expired?'EXPIRED':$order->status;
				return $status;
			})
			->editColumn('tostatus', function($order){
				return $order->counter->status;
			})
			->editColumn('reference', function($order){
				return '<a href="'.route('order',$order->reference).'">
							'.$order->reference.'
						</a>';
			})->toJson();
    }
	


}
