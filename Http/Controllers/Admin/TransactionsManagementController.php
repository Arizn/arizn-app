<?php

namespace App\Http\Controllers\Admin;

use App\Models\Profile;
use App\Models\Transaction;
use Auth;
use Illuminate\Http\Request;
use jeremykenedy\LaravelRoles\Models\Role;
use Validator;
use Illuminate\Support\Facades\Form;
use Illuminate\Support\Facades\URL;
use Yajra\Datatables\Datatables;

class TransactionsManagementController extends \App\Http\Controllers\Controller
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
		return View('_admin.show-transactions');
	}
	
	public function table(){
		$tx = Transaction::query();
		return Datatables::of($tx)
			->escapeColumns([])
			->addColumn('symbol', function ($tx) {
				return  $tx->token->symbol;
			})
			->editColumn('amount', function ($tx) {
				return  $tx->amount.' '.$tx->token->symbol;
			})
			->editColumn('tx_hash', function ($tx) {
				return ' <a  href="https://etherscan.io/tx/'.$tx->tx_hash.'" data-toggle="tooltip" title="Refresh Tx">'.substr($tx->tx_hash,0,18).'.....
						 </a>';
			})
			->addColumn('user', function ($token) {
				return  $token->user->name;
			}) ->toJson();
	}
	
	
}
