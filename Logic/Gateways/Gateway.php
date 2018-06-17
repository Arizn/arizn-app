<?php
namespace App\Logic\Gateways;
use Illuminate\Http\Request;

interface Gateway {
	//protected $view ;
	//protected $gate;
	//protected $sendHash;
	//protected $collectHash ;
	public function payout( );
	public function form_validation( );
	public function collect(Request $request);
	public function ipn();
	public function form();
	public function isRedirect();
	public function getView();
	public function redirect();
	
	
}