<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Traits\WalletTrait;
use App\Traits\OrderTrait;
use Illuminate\Support\Facades\Hash;
use App\Models\Account;
use App\Models\Order;

class ApiController extends Controller
{
	use WalletTrait, OrderTrait;
    //
	public function newOrder(Request $request){
		$user = auth()->user();
		$account = $user->account;
		$request->validate([
            'amount'  => 'required|numeric',
            'symbol' => 'required',
			'return_url' => 'required',
			'cancel_url' => 'required',
			'ipn_url' => 'required',
        ]);
		
		if (is_null($account)) {
			return response()->json(['error'=>'INVALID APIKEY']);
		}
		if (!$request->is('api/*')) {
			return response()->json(['error'=>'FORBIDDEN']);
		}
		$data = $request->all();
		$fees = setting('fees',0);
		if($fees)$fees  = number_format($fees /100*$request->input('amount'),8);
		$exp = isset($data['expiry'])?$data['expiry']:setting('tx_validity',10);
		$data['expiry'] = $exp;
		$token =\App\Models\Token::symbol($request->input('symbol'))->first();
		$order = new Order();
		if($token->family=='bitfamily'){
			list($order->idx,$order->address) = $this->deriveCoinAddress($account);
		}else{
			list($order->idx,$order->address) = $this->deriveAddress($account);
		}
		$order->item = 'api'; 
		$order->reference = md5(time());
		$order->expires_at = \Carbon\Carbon::now()->addMinutes((int)$exp);
		$order->item_url = $request->input('ipn_url');
		$order->account_id = $account->id;
		$order->user_id = NULL;
		$order->token_id = $token->id;
		$order->item_data = $data;
		$order->item_data = $data;
		$order->amount = $request->input('amount');
		$order->fees = $fees;
		$order->type = 'collect';
		$order->gateway = $token->name;
		$order->token_type = '\App\Models\Token';
		$order->symbol = $request->input('symbol');
		$order->save();
		return response()->json(['reference'=>route('order',$order->reference)]);	
	}
	
	public function orderStatus($reference){
		$user = auth()->user();
		$account = $user->account;
		$order = $account->orders()->where('reference',$reference)
						 ->with(['transactions' => function ($query) {
							  $query->where('type', 'credit');
						   }])
						 ->first();
						 
		if (is_null($order)) {
			return response()->json(['error'=>'NOT FOUND']);
		}
		
		return $order;
	}
	
	public function payout(Request $request){
		$user = auth()->user();
		$account = $user->account;
		$payroll = json_decode($request->input('payroll'));
		if(json_last_error()!=JSON_ERROR_NONE){
			switch (json_last_error()) {
				case JSON_ERROR_DEPTH:
					$error = 'The maximum stack depth has been exceeded.';
					break;
				case JSON_ERROR_STATE_MISMATCH:
					$error = 'Invalid or malformed JSON.';
					break;
				case JSON_ERROR_CTRL_CHAR:
					$error = 'Control character error, possibly incorrectly encoded.';
					break;
				case JSON_ERROR_SYNTAX:
					$error = 'Syntax error, malformed JSON.';
					break;
				// PHP >= 5.3.3
				case JSON_ERROR_UTF8:
					$error = 'Malformed UTF-8 characters, possibly incorrectly encoded.';
					break;
				// PHP >= 5.5.0
				case JSON_ERROR_RECURSION:
					$error = 'One or more recursive references in the value to be encoded.';
					break;
				// PHP >= 5.5.0
				case JSON_ERROR_INF_OR_NAN:
					$error = 'One or more NAN or INF values in the value to be encoded.';
					break;
				case JSON_ERROR_UNSUPPORTED_TYPE:
					$error = 'A value of a type that cannot be encoded was given.';
					break;
				default:
					$error = 'Unknown JSON error occured.';
					break;
			}
			return response()->json(['error'=>strtoupper($error)]);
		}
		$ret = [];
		$tokens = [];
		foreach( $payroll as $payee){
			$ret[$payee->address ] = NULL;
			$token = isset($tokens[$payee->coin])?$tokens[$payee->coin]: \App\Models\Token::symbol($payee->coin)->first();
			$tokens[$payee->coin] = $token;
			if($payee->coin = 'ETH' )
			$token = NULL;
			try{
				$ret[$payee->address ] =[ 
					'RESPONSE'=>'SUCCESS',
					'TXID'=> $this->send(
						$payee->amount , 
						$payee->address, 
						$account, 
						$request->input('password'), 
						$token,
						NULL,
						NULL
				 )] ;
			}catch(Exception $e){
				$ret[$payee->address]= ['RESPONSE'=>'ERROR',
										"ERROR"=>$e->getMessage()];
			}
		}
		
		return response()->json($ret);
	}
	
	
	public function accessToken(Request $request){
		$user = auth()->user();
		$password = $request->input('password');
		if (Hash::check($password, $user->password)) {
			$account = $user->account;
			if(empty($account->api_key)){
				$account->api_key = $user->createToken('Merchant-Key')->accessToken;
				$account->save();
			}
			return response()->json(['filename'=>'rsa apikey','file'=>$account->api_key,'status' => 'SUCCESS','message' => 'Api Key Download Has started']);
		
		}
		return response()->json(['status' => 'ERROR','message' => 'Invalid Password. Please Check your password']);
		
		
	}
	
	
	
	
}
