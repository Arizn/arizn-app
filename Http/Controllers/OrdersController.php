<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
use \App\Models\Token;
use \App\Models\Order;
use Illuminate\Http\Request;
use jeremykenedy\LaravelRoles\Models\Role;
use Illuminate\Support\Facades\View;
class OrdersController extends Controller
{
	use \App\Traits\OrderTrait;
	//use \App\Traits\WalletTrait;
    //
	public function __construct() { 
		//$this->middleware('auth')->only('payWithToken');
	}
	
	
	
	public function payWithWallet(Request $request){
		
		$request->validate(['order_id'  => 'required']);
		$order = Order::with('token')->findOrFail($request->input('order_id'));
		$gateway = $order->token->gateways->groupBy('name')->get($order->gateway);
		$gateway = isset($gateway[0])?$gateway[0]:NULL;
		if(empty($gateway)||!in_array('collect',$gateway['functions'])){
			$message ='<br>: COULD NOT RESOLVE GATEWAY .Send Tx Failed';
			$order->logg .= $message;
			$order->save();
			return response()->json(['status' => 'ERROR','message' => $message]);
		}else{
			$gate = $gateway['class'];
			$paygate = new $gate($order);
			return  $paygate->collect($request);
		}
		
	}
	
	public function ipn($reference){
		$order =   \App\Models\Order::withoutGlobalScopes()->with('token')->where('reference',$reference)->first();
		$gateway = $order->token->gateways->groupBy('name')->get($order->gateway);
		$gateway = isset($gateway[0])?$gateway[0]:NULL;
		if(empty($gateway)||!in_array('collect',$gateway['functions'])){
			$message ='<br>: COULD NOT RESOLVE GATEWAY .Send Tx Failed';
			$order->logg .= $message;
			$order->save();
			return response()->json(['status' => 'OK']);
		}else{
			$gate = $gateway['class'];
			$paygate = new $gate($order);
			return  $paygate->ipn($request);
		}
	}
	
	public function CompletePayment($reference){
		$order = \App\Models\Order:: withoutGlobalScopes()->with(['account','counter'])->where('reference',$reference)->first(); 
		
		$remainingTime =  $order->expires_at->diffInSeconds(\Carbon\Carbon::now());
		$expired =  \Carbon\Carbon::now()->greaterThan($order->expires_at);
		$adminRole = Role::where('slug','admin')->firstOrFail();
		$admin = $adminRole->users()->firstOrFail();
		$siteOrder = $order->account->id == $admin->account->id;
		$timer = false;
		$update = true;
		if($expired){
			$remainingTime = 0-$remainingTime;
		}
	
		if($order->status =="UNPAID"){
			$status = "UNPAID";
			$gateway = $order->token->gateways->groupBy('name')->get($order->gateway);
			$gateway = isset($gateway[0])?$gateway[0]:NULL;
			if(empty($gateway)||!in_array('collect',$gateway['functions'])){
				$message ='<br>: COULD NOT RESOLVE GATEWAY .Send Tx Failed';
				$order->logg .= $message;
				$order->save();
				$form  = "<div> <h2>COULD NOT RESOLVE PAYMENT GATEWAY. PLEASE CONTACT SUPPORT</h2></div>";
			}else{
				$gate = $gateway['class'];
				$paygate = new $gate($order);
				$gateform  = $paygate->form();
				if($gateform->isRedirect()){
					return $gateform->redirect();
				}else{
					$form = $gateform->getView()->render();
					$timer = true;
				}
			}
			
			
		}
		if($order->status =="CONFIRMING"){
			$status = "CONFIRMING";
			$form =  View::make('orders.status', compact('order','siteOrder','timer'));
		}
		if($order->status =="COMPLETE" && $order->counter()->count() < 1){
			$update = false;
			$status = "COMPLETENOCOUNT";
			$form =  View::make('orders.status', compact('order','siteOrder','timer'));
		}elseif($order->status =="COMPLETE" && $order->counter()->count() > 0){
			$status = "COMPLETE";
			if($order->counter->status =="UNPAID")
			$status = "PROCESSING";
			if($order->counter->status =="COMPLETE")
			$status = "DELIVERED";
			$timer = false;
			$update = false;
			$form =  View::make('orders.status', compact('order','siteOrder','timer'));
			
		}
		
		return view('orders.order', compact('order','expired','status','remainingTime','form','timer','update'));
		
		
		
		
		
	}
	
	public function status($reference){
		$order = \App\Models\Order:: with(['counter','transactions'])->where('reference',$reference)->first();
		$remainingTime =  $order->expires_at->diffInSeconds(\Carbon\Carbon::now());
		$expired =  \Carbon\Carbon::now()->greaterThan($order->expires_at);
		$adminRole = Role::where('slug','admin')->firstOrFail();
		$admin = $adminRole->users()->firstOrFail();
		$siteOrder = $order->account->id == $admin->account->id;
		$timer = false;
		
		if($order->status =="UNPAID"){
			if($expired){
				return response()->json(['status' => 'EXPIRED']);
			}
			return response()->json(['status' => 'UNPAID']);
			
		}
		if($order->status =="CONFIRMING"){
			$status = "CONFIRMING";
		}
		if($order->status =="COMPLETE" && $order->counter()->count() < 1){
			$status = "COMPLETENOCOUNT";
		}
		if($order->status =="COMPLETE" && $order->counter()->count() > 0){
			$status = "COMPLETE";
			if($order->counter->status =="UNPAID")
			$status = "PROCESSING";
			if($order->counter->status =="COMPLETE")
			$status = "DELIVERED";
		}
		$html =  View::make('orders.status', compact('order','siteOrder','timer'));
		return response()->json(['status' => $status ,'html'=>$html->render()]);
		
	}

}
