<?php

namespace App\Http\Controllers;

use App\Models\Token;
use App\Models\Contract;
use App\Models\Country;
use File;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Session;
use Image;
use jeremykenedy\Uuid\Uuid;
use Validator;
use View;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\Auth;

class WelcomeController extends Controller
{
    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function welcome()
    {

		$token = NULL;
		if(setting('APP_MODE','ICO')==='ICO'){
			
			$token = \App\Models\Token::symbol(setting('siteToken','ETH'))->LiveNow()->first();
			if(!is_null($token))
			return view('home.ico', compact('token'));
		}
		$tokens = Token::where('sale_active', 1)->orderBy('created_at', 'desc')->take(6)->get();
		return view('home.welcome',compact('tokens')); 
	}
	
	 public function icoindex()
    {
		$tokens = Token::latest('change_pct')->whereIn('symbol', explode('|', setting('dashBoardTokens')))->take(6)->get();
        $table =route('home.icos.table');
        return view('home.tokens.icos', compact('table','tokens'))->with(['title'=>'ICO <small> <a href="'.route('myicos').'" >  Show Mine</a></small>']);
    }
	
	
	 public function tokenindex()
    {
		$tokens = Token::latest('change_pct')->whereIn('symbol', explode('|', setting('dashBoardTokens')))->take(6)->get();
        $table =route('home.tokens.table');
        return view('home.tokens.list', compact('table','tokens'))->with(['title'=>'ICO <small> <a href="'.route('myicos').'" >  Show Mine</a></small>']);
    }
	
	/**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            $token = Token::symbol($id)->firstOrFail();
        } catch (ModelNotFoundException $exception) {
            abort(404); 
        }
		$country = Country::site()->first();
		if($token->isIco)
        return view('home.tokens.ico')->with(compact('token', 'country'));
		return view('home.tokens.token')->with(compact('token', 'country'));   
    }
	
	
	
	
	public function table()
    {
	  $table = Datatables::of(Token::query());
	  $country = Country::site()->firstORFail();	
      return $table
	  		->escapeColumns([])
			->editColumn('logo', function($token){
				$rt = !Auth::check()?route('home.info.tokens',$token->symbol):route('coins.show',$token->symbol);
				return '<a href="'.$rt.'">
							<img src="'.route('home.coins.image',$token->logo).'" class="img-circle token_logo" alt="'.$token->name.'" title="'.$token->name.'"/>
						</a>';
			})
			
			->editColumn('price', function($token)use($country){
				
				return $country->symbol.number_format($token->price,$country->decimal_digits);
			})
			->editColumn('volume', function($token)use($country){
				return number_format($token->volume,$country->decimal_digits);
			})
			->editColumn('market_cap', function($token)use($country){
				return number_format($token->market_cap,$country->decimal_digits);
			})
			->editColumn('change_pct', function($token){
				return $token->change_pct > 0?'<span class="green">'.$token->change_pct.'</span>':'<span class="red">'.$token->change_pct.'</span>';
			})
			
			
			->editColumn('name', function($token){
				$rt = !Auth::check()?route('home.info.tokens',$token->symbol):route('coins.show',$token->symbol);
				$name ='<span>
							<a data-placement="right" data-toggle="tooltip" title="View Details"  href="'.$rt.'">'.substr($token->name,0,15).'</a>
						</span>';
				if( $token->wallet_active==1){					$bal = 'Wallet';
					$link = route('public.home');
					$link_class = ' ';
					$badge_class = 'badge-danger';
					$name.='&nbsp;&nbsp;<a data-placement="right" data-toggle="tooltip" title="Create a Wallet for '.$token->name.' " type="button" data-token_id="'.$token->id.'" href="'.$link.'" class="'.$link_class.' btn-sm btn btn-primary tooltips">
						<span class="badge '.$badge_class.'">'.$bal.'</span>
						</a>';
				}
				return $name;
			})
			->toJson();
	    
    }
	
	
	public function icotable( )
    {	  
	  $country = Country::site()->firstORFail();	
	  $table = Datatables::of(Token::byType('ico'));
      return $table
	  		->escapeColumns([])
			->editColumn('logo', function($token){
				$rt = !Auth::check()?route('home.info.tokens',$token->symbol):route('coins.show',$token->symbol);
				return '<a href="'.$rt.'">
							<img src="'.route('home.coins.image',$token->logo).'" class="img-circle token_logo" alt="'.$token->name.'" title="'.$token->name.'"/>
						</a>';
			})
			
			->editColumn('price', function($token)use($country){
				$price = $token->symbol.number_format($token->price, 2);
				if($token->sale_active && $token->isLive)
				$price.='&nbsp;<a data-placement="right" data-toggle="tooltip" title="Join The ICO" class="btn btn-sm btn-danger" href="'.route('coins.show',$token->symbol).'"><i class="fa fa-shopping-cart"></i> BUY NOW</a>';
				return $price;
				
			})
			
			->editColumn('change_pct', function($token){
				return empty($token->change_pct) ?'<span class="red">0.00%</span>':'<span class="green">'.$token->change_pct.'%</span>';
			})
			
			->editColumn('name', function($token){
				$rt = !Auth::check()?route('home.info.tokens',$token->symbol):route('coins.show',$token->symbol);
				$name ='<span>
							<a data-placement="right" data-toggle="tooltip" title="View Details"  href="'.$rt.'">'.substr($token->name,0,15).'</a>
						</span>';
				if($token->wallet_active==1){
					$bal = 'Wallet';
					$link = route('public.home');
					$link_class = ' ';
					$badge_class = 'badge-danger';
					$title = 'Add to My Wallets';
					$name.='&nbsp;&nbsp;<a data-placement="right" data-toggle="tooltip" title="'.$title.'" type="button" data-token_id="'.$token->id.'" href="'.$link.'" class="'.$link_class.' btn-sm btn btn-primary tooltips">
						<span class="badge '.$badge_class.'">'.$bal.'</span>
						</a>';
				}
				return $name;
			})
			->toJson();
	    
    
	}
	 public function image($image)
    {
		$image = explode('@',$image);
		$img = Image::make(storage_path().'/images/'.$image[0]);
		if(isset($image[1])&&!empty($image[1])){
			list($w,$h) = explode('x',strtolower($image[1]));
			$img->fit($w, $h);
		}
        return $img->response();
    }

	
        
}
