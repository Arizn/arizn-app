<?php

namespace App\Http\Controllers\Admin;

use App\Models\Profile;
use App\Models\Order;
use Auth;
use Illuminate\Http\Request;
use jeremykenedy\LaravelRoles\Models\Role;
use Validator;
use Illuminate\Support\Facades\Form;
use Illuminate\Support\Facades\URL;
use Yajra\Datatables\Datatables;

class OrdersManagementController extends \App\Http\Controllers\Controller
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
		return View('_admin.show-orders');
	}
	
	
	public function table(){
		$tx = Order::whereNull('counter_id')->get()->makeVisible('id')->makeVisible('item')->makeVisible('created_at');
		return Datatables::of($tx)
			->escapeColumns([])
			->editColumn('amount', function ($tx) {
				$name = 'Pending';
				$label = 'default';
				if($tx->active == 0){
					$name = 'Complete';
					$label = 'success';
				}
				if($tx->active == 2){
					$name = 'Failed';
					$label = 'danger';
				}
				return '<span data-original-title="'.$name.'" title="'.$name.'" class="btn btn-sm btn-'.$label.' btn-block tooltips"  data-toggle="tooltip" title="Edit">'.$tx->amount.$tx->symbol.'</span>';
	
			})
			->editColumn('item', function ($tx) {
				if($tx->item !='exchange'||$tx->item !='market')return $tx->item;
				$name = 'Pending';
				$label = 'warning';
				if($tx->active == 0){
					$name = 'Complete';
					$label = 'success';
				}
				if($tx->active == 2){
					$name = 'Failed/Cancelled';
					$label = 'danger';
				}
				
				if($tx->counter->active != 1){
				
				return '
				<div class="btn-group">
                  <button type="button" class="btn btn-info">Action</button>
                  <button type="button" class="btn btn-info dropdown-toggle" data-toggle="dropdown">
                    <span class="caret"></span>
                    <span class="sr-only">show/hide</span>
                  </button>
                  <ul class="dropdown-menu" role="menu">
                    <li><a href="'.route('admin.orders.complete',$tx->id).'">Complete</a></li>
                    <li><a href="'.route('admin.orders.cancel',$tx->id).'">Cancel</a></li>
                  </ul>
                </div>';
				}else{
					return '<span data-original-title="'.$name.'" title="'.$name.'" class="btn btn-sm btn-'.$label.' btn-block" >'.$name.'</span>';
				}
	
			})
			->addColumn('user', function ($token) {
				return  $token->user->name;
			}) ->toJson();
	}
	
	
	public function complete($id){
		$order = \App\Order::find($id);
		if($order)
		$order->complete();
	}
	
	public function cancel($id){
		$order = \App\Order::find($id);
		if($order)
		$order->active == 2;
		$order->save();
		if($order->counter()->count()){
			$order->counter->active == 2;
			$order->counter->save();
		}
	}
	
	
}
