<?php
namespace App\Logic\Gateways;
use App\Logic\Gateways\Gateway;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use \App\Models\Token;
use \App\Models\Order;
use Illuminate\Http\Request;
use jeremykenedy\LaravelRoles\Models\Role;

class Paypal implements Gateway {
	protected $view = NULL;
	protected $gate = NULL;
	protected $sendHash = NULL;
	protected $collectHash = NULL;
	public function __construct($order){
		$this->order = $order;
		
	}
	public function send( ){
		
		 
	}
	
	public function form_validation( ){
		return [];
		 
	}
	
	public function collect(Request $request){
		return NULL;
	}
	
	
	public function ipn(){
		return false;
	}
	
	
	public function form(){
		$items = collect([]);
		$adminRole = Role::where('slug','admin')->firstOrFail();
		$admin = $adminRole->users()->firstOrFail();
		$siteOrder = $this->order->account->id == $admin->account->id;
		if(isset($this->order->item_data['items'])){
			$items = collect( $this->order->item_data['items'] );
		}
		$this->view =  View::make('gateways.blockchain', compact('order'));
		return $this;
	}
	
	public function isRedirect( ){
		return !is_null($this->gate)&&is_null($this->view);
	}
	
	public function getView($order){
		return $this->view;
	}
	
	public function redirect($order){
		if(is_string($this->gate)){
			return redirect($this->gate);
		}else{
			$this->gate->redirect();
		}
	}
	
	
}