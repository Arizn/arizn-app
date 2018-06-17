<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Ixudra\Curl\Facades\Curl;
trait OrderTrait
{
	
	
    public function completeOrder(\App\Models\Order $order)
    {
		//if($order->status == 'COMPLETE')return true;
        $transactions = $order->transactions()->where('type','credit')->get();
		$total = $transactions->sum('amount');
		if($total < $order->amount && $order->amount > 0){
			$message.='<br>: Order Total Credit ('.$total.') is less than order amount ('.$order->amount.') Please send More '.($order->amount - $total).$order->token->symbol.' to'.$order->address;
			$order->logg .= $message;
			$order->save();
			return false;
		}
		$order->active = 1;
		$order->status = 'COMPLETE';
		$order->save();
		// activate post;
		if($order->item =='token'){
			$token = \App\Models\Token::find($order->item_id);
			$token->active = 1;
			$token->save();
			return true;
		}
		if($order->item =='token_ico'){
			$token = \App\Models\Token::find($order->item_id);
			$token->active = 1;
			$token->ico_active = 1;
			$token->save();
			return true;
		}
		if($order->item =='token_wallet'){
			$token = \App\Models\Token::find($order->item_id);
			$token->active = 1;
			$token->ico_active = 1;
			$token->wallet_active = 1;
			$token->save();
			return true;
		}
		if($order->item =='api'){
			 $response = Curl::to($order->item_url)
				->withData( $order->toArray() )
				->asJson()
				->post();
			return true;
		}
		if($order->item =='exchange'&&$order->type =='collect'){
			return $this->pay($order->counter);
		}
		return true;
    }

	public function pay($order){
		$gateway = $order->token->gateways->groupBy('name')->get($order->gateway);
		$gateway = isset($gateway[0])?$gateway[0]:NULL;
		if(empty($gateway)||!in_array('collect',$gateway['functions'])){
			$message ='<br>: COULD NOT RESOLVE GATEWAY .Send Tx Failed';
			$order->logg .= $message;
			$order->save();
			return false;
		}
		$gate = $gateway['class'];
		$paymentOrder = new $gate($order);
		return $paymentOrder->payout();
	}
	
	
	public function gateways($order){
		
		if($order->token instanceof \App\Models\Token ){
			return collect([
				'name'=>$order->token->name,
				'logo'=>$order->token->image, // storage/gateways
				'class'=>'\App\Logic\Gateways\Blockchain',
				'currency' => $order->token->symbol,
				'function'=>['send','collect'],
			]);
		}
		
		if($order->token instanceof \App\Models\Country ){
			$config = collect(config('gateways.gates'));
			return $config->filter(function($gate,$key)use($order){
				return in_array($order->token->currency,$gate['currencies'])&&in_array($order->type,$gate['function']);
			});
		}
	}
	
	public function gateway( $order, $name = NULL){
		
		if($order->token instanceof \App\Models\Token ){
			return new \App\Logic\Gateways\Blockchain($order);
		}
		
		if($order->token instanceof \App\Models\Country ){
			if(is_null($name))throw new Exception('Specify the Gateway to name');
			$config = collect(config('gateways.gates'));
			$gate = $config->groupBy('name')->get($name);
			$gateway  = $gate['class'];
			return new $gateway($order);
		}
	}

    
}

		
		
	
