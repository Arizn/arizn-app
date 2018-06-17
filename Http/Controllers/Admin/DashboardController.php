<?php

namespace App\Http\Controllers\Admin;
use \App\Models\Token;
use \App\Models\User;
use \App\Models\Order;
use \App\Http\Requests;
use \Carbon\Carbon;
use \Illuminate\Http\Request;

class DashboardController extends \App\Http\Controllers\Controller
{
	use \App\Traits\WalletTrait;
    public function __construct()
    {
		$this->middleware('auth');
    }

  public function home(Token $tokens, User $users , Order $orders)
  {
        $rangetoday = Carbon::now()->subDays(1);
        $tokenlivecount = $tokens->approve('yes')->count();
		$totalOrders = $orders->completed()->sum('amount');
        $todaytoken = $tokens->count();
        $todayusers = $users->where('created_at', '>=', $rangetoday)->count();
        $todaysales = $orders->completed()->where('updated_at', '>=', $rangetoday)->count();
        $icocount = $tokens->byType('ico')->count();
        $icolivecount = $tokens->byType('ico')->liveNow()->count();
        $walletcount = $tokens->byType('wallet')->count();
        $lastunapproved =$tokens->approve('no')->take('10')->latest("updated_at")->get();
        $lastico =$tokens->approve('yes')->byType('ico')->latest("updated_at")->take('5')->get();
        $lastorders = $orders->latest("created_at")->take('20')->get();
        $lastusers = $users->latest("created_at")->take('10')->get();
		
        return view('_admin.index', compact(
            'todaytoken',
			'totalOrders',
            'tokenlivecount',
            'todayusers',
            'todaysales',
            'icocount',
            'icolivecount',
            'lastunapproved',
            'lastico',
            'lastorders',
            'lastusers',
            'updateversion'
        ));
    }
	
	 /**
     * Saves Settings.
     *
     * @return \Illuminate\Http\Response
     */
    public function settings(Request $request)
    {
		if(!is_null($request->input('promowidget'))){
			\Setting::set('promowidget', $request->input('promowidget'));
			\Setting::save();
			return response()->json(['status' => 'SUCCESS','message' => trans('admin.promowidget').' Saved Successfully']);
		}
		if(!is_null($request->input('maintenance'))){
			if(env('DEMO',true)){
				return response()->json(['URL'=>route('admin.home'),'status' => 'ERROR','message' => 'Disabled in DEMO MODE. site is Being showcased. You can turn this off after you purchase']);
			}
			\Setting::set('maintenance', $request->input('maintenance'));
			\Setting::save();
			return response()->json(['status' => 'SUCCESS','message' => trans('admin.maintenance').' Saved Successfully']);
		}
		if(!is_null($request->input('enableDeploy'))){
			if(env('DEMO',true)){
				return response()->json(['URL'=>route('admin.home'),'status' => 'ERROR','message' => 'Disabled in DEMO MODE. Being showcased. You can turn this off after you purchase']);
			}
			\Setting::set('enableDeploy', $request->input('enableDeploy'));
			\Setting::save();
			return response()->json(['status' => 'SUCCESS','message' => trans('admin.enableDeploy').' Saved Successfully']);
		}
		if(!is_null($request->input('enableExchange'))){
			if(env('DEMO',true)){
				return response()->json(['URL'=>route('admin.home'),'status' => 'ERROR','message' => 'Disabled in DEMO MODE. Being showcased. You can turn this off after you purchase']);
			}
			\Setting::set('enableExchange', $request->input('enableExchange'));
			\Setting::save();
			return response()->json(['status' => 'SUCCESS','message' => trans('admin.enableExchange').' Saved Successfully']);
		}
		
		
		
		
		
		
		if(!is_null($request->input('enablesitewidewallet'))){
			\Setting::set('enablesitewidewallet', $request->input('enablesitewidewallet'));
			\Setting::save();
			return response()->json(['status' => 'SUCCESS','message' => trans('admin.enablesitewidewallet').' Saved Successfully']);
		}
		if(!is_null($request->input('distributeTokenprice'))){
			\Setting::set('distributeTokenprice', $request->input('distributeTokenprice'));
			\Setting::save();
			return response()->json(['status' => 'SUCCESS','message' => trans('admin.distributeTokenprice').' Saved Successfully']);
		}
		if(!is_null($request->input('showbuybuttonprice'))){
			\Setting::set('showbuybuttonprice', $request->input('showbuybuttonprice'));
			\Setting::save();
			return response()->json(['status' => 'SUCCESS','message' => trans('admin.showbuybuttonprice').' Saved Successfully']);
		}
		if(!is_null($request->input('tx_validity'))){
			\Setting::set('tx_validity', $request->input('tx_validity'));
			\Setting::save();
			return response()->json(['status' => 'SUCCESS','message' => trans('admin.tx_validity').' Saved Successfully']);
		}
		if(!is_null($request->input('tokencreationprice'))){
			\Setting::set('tokencreationprice', $request->input('tokencreationprice'));
			\Setting::save();
			return response()->json(['status' => 'SUCCESS','message' => trans('admin.tokencreationprice').' Saved Successfully']);
		}
		if(!is_null($request->input('tokenlistingprice'))){
			\Setting::set('tokenlistingprice', $request->input('tokenlistingprice'));
			\Setting::save();
			return response()->json(['status' => 'SUCCESS','message' => trans('admin.tokenlistingprice').' Saved Successfully']);
		}
        if(!is_null($request->input('listingsettings'))){
			\Setting::set('listingsettings', $request->input('listingsettings'));
			\Setting::save();
			return response()->json(['status' => 'SUCCESS','message' => trans('admin.listingsettings').' Saved Successfully']);
		}
		if(!is_null($request->input('icolistingprice'))){
			\Setting::set('icolistingprice', $request->input('icolistingprice'));
			\Setting::save();
			return response()->json(['status' => 'SUCCESS','message' => trans('admin.icolistingprice').' Saved Successfully']);
		}
		
		if(!is_null($request->input('INFURATOKEN'))){
			if(env('DEMO',true)){
				return response()->json(['URL'=>route('admin.home'),'status' => 'ERROR','message' => 'Disabled in DEMO MODE. This is required for demo to work']);
			}
			\Setting::set('INFURATOKEN', $request->input('INFURATOKEN'));
			\Setting::save();
			return response()->json(['status' => 'SUCCESS','message' => trans('admin.INFURATOKEN').' Saved Successfully']);
		}
		
		
		if(!is_null($request->input('ETHERSCANTOKEN'))){
			if(env('DEMO',true)){
				return response()->json(['URL'=>route('admin.home'),'status' => 'ERROR','message' => 'Disabled in DEMO MODE. This is required for demo to work']);
			}
			\Setting::set('ETHERSCANTOKEN', $request->input('ETHERSCANTOKEN'));
			\Setting::save();
			return response()->json(['status' => 'SUCCESS','message' => trans('admin.ETHERSCANTOKEN').' Saved Successfully']);
		}
		if(!is_null($request->input('PARITYIP'))){
			\Setting::set('PARITYIP', $request->input('PARITYIP'));
			\Setting::save();
			return response()->json(['status' => 'SUCCESS','message' => trans('admin.PARITYIP').' Saved Successfully']);
		}
		if(!is_null($request->input('ETHEREUMNETWORK'))){
			if(env('DEMO',true)){
				return response()->json(['URL'=>route('admin.home'),'status' => 'ERROR','message' => 'Disabled in DEMO MODE. Using Ropsten ONLY in the Demo.']);
			}
			\Setting::set('ETHEREUMNETWORK', $request->input('ETHEREUMNETWORK'));
			\Setting::save();
			\App\Models\Last::truncate();
			return response()->json(['status' => 'SUCCESS','message' => trans('admin.ETHEREUMNETWORK').' Saved Successfully']);
		}
		if(!is_null($request->input('ETHEREUMPROVIDER'))){
			\Setting::set('ETHEREUMPROVIDER', $request->input('ETHEREUMPROVIDER'));
			\Setting::save();
			return response()->json(['status' => 'SUCCESS','message' => trans('admin.ETHEREUMPROVIDER').' Saved Successfully']);
		}
		
		if(!is_null($request->input('openExchangeRatesApiKey'))){
			\Setting::set('openExchangeRatesApiKey', $request->input('openExchangeRatesApiKey'));
			\Setting::save();
			return response()->json(['status' => 'SUCCESS','message' => trans('admin.openExchangeRatesApiKey').' Saved Successfully']);
		}
		if(!is_null($request->input('dashBoardTokens'))){
			\Setting::set('dashBoardTokens', $request->input('dashBoardTokens'));
			\Setting::save();
			return response()->json(['status' => 'SUCCESS','message' => trans('admin.dashBoardTokens').' Saved Successfully']);
		}
		
		if(!is_null($request->input('siteToken'))){
			try{
			$token = \App\Models\Token::where('symbol',$request->input('siteToken'))->firstOrFail();
			}catch(\Exception $e){
				return response()->json(['status' => 'ERROR','message' => 'Invalid Token Doesnt Exist.']);
			}
			\Setting::set('siteToken', $token->symbol);
			\Setting::save();
			return response()->json(['status' => 'SUCCESS','message' => trans('admin.siteToken').' Saved Successfully']);
		}
		
		if(!is_null($request->input('siteCurrency'))){
			\Setting::set('siteCurrency', $request->input('siteCurrency'));
			\Setting::save();
			return response()->json(['status' => 'SUCCESS','message' => trans('admin.siteCurrency').' Saved Successfully']);
		}
		
		
		if(!is_null($request->input('coinNetwork'))){
			if(env('DEMO',true)){
				return response()->json(['URL'=>route('admin.home'),'status' => 'ERROR','message' => 'Disabled in DEMO MODE. Fixed to Testnet.']);
			}
			\Setting::set('coinNetwork', $request->input('coinNetwork'));
			\Setting::save();
			return response()->json(['URL'=>route('admin.home'),'status' => 'SUCCESS','message' => trans('admin.coinNetwork').' Saved Successfully']);
		}
		if(!is_null($request->input('defaultFees'))){
			\Setting::set('defaultFees', $request->input('defaultFees'));
			\Setting::save();
			return response()->json(['URL'=>route('admin.home'),'status' => 'SUCCESS','message' => trans('admin.defaultFees').' Saved Successfully']);
		}
		
		if(!is_null($request->input('minConf'))){
			\Setting::set('minConf', $request->input('minConf'));
			\Setting::save();
			return response()->json(['URL'=>route('admin.home'),'status' => 'SUCCESS','message' => trans('admin.minConf').' Saved Successfully']);
		}
		
		/*/ enable disable stuff
		*enable diable site moduels
		*/
		
		
		
		
		if(!is_null($request->input('enableForum'))){
			if(env('DEMO',true)){
				return response()->json(['URL'=>route('admin.home'),'status' => 'ERROR','message' => 'Disabled in DEMO MODE. Being showcased for the Demo.']);
			}
			\Setting::set('enableForum', $request->input('enableForum'));
			\Setting::save();
			return response()->json(['URL'=>route('admin.home'),'status' => 'SUCCESS','message' => trans('admin.enableForum').' Saved Successfully']);
		}
		
		
		if(!is_null($request->input('enablePayGateway'))){
			if(env('DEMO',true)){
				return response()->json(['URL'=>route('admin.home'),'status' => 'ERROR','message' => 'Disabled in DEMO MODE. Being showcased for the Demo.']);
			}
			\Setting::set('enablePayGateway', $request->input('enablePayGateway'));
			\Setting::save();
			return response()->json(['URL'=>route('admin.home'),'status' => 'SUCCESS','message' => trans('admin.enablePayGateway').' Saved Successfully']);
		}
		
		if(!is_null($request->input('membersOnlyExchange'))){
			if(env('DEMO',true)){
				return response()->json(['URL'=>route('admin.home'),'status' => 'ERROR','message' => 'Disabled in DEMO MODE. Being showcased for the Demo.']);
			}
			\Setting::set('membersOnlyExchange', $request->input('membersOnlyExchange'));
			\Setting::save();
			return response()->json(['URL'=>route('admin.home'),'status' => 'SUCCESS','message' => trans('admin.membersOnlyExchange').' Saved Successfully']);
		}
		
		
		/*/colde key
		* settingd for the app cold keys
		*/
		if(!is_null($request->input('LTC_coldKey'))){
			if(env('DEMO',true)){
				return response()->json(['URL'=>route('admin.home'),'status' => 'ERROR','message' => 'Disabled in DEMO MODE. Swapping cold keys will invalidate any address already created.']);
			}
			\Setting::set('LTC_coldKey', $request->input('LTC_coldKey'));
			\Setting::save();
			return response()->json(['URL'=>route('admin.home'),'status' => 'SUCCESS','message' => trans('admin.LTC_coldKey').' Saved Successfully']);
		}
		
		if(!is_null($request->input('ZEC_coldKey'))){
			if(env('DEMO',true)){
				return response()->json(['URL'=>route('admin.home'),'status' => 'ERROR','message' => 'Disabled in DEMO MODE. Swapping cold keys will invalidate any address already created.']);
			}
			\Setting::set('ZEC_coldKey', $request->input('ZEC_coldKey'));
			\Setting::save();
			return response()->json(['URL'=>route('admin.home'),'status' => 'SUCCESS','message' => trans('admin.ZEC_coldKey').' Saved Successfully']);
		}
		
		if(!is_null($request->input('DASH_coldKey'))){
			if(env('DEMO',true)){
				return response()->json(['URL'=>route('admin.home'),'status' => 'ERROR','message' => 'Disabled in DEMO MODE. Swapping cold keys will invalidate any address already created.']);
			}
			\Setting::set('DASH_coldKey', $request->input('DASH_coldKey'));
			\Setting::save();
			return response()->json(['URL'=>route('admin.home'),'status' => 'SUCCESS','message' => trans('admin.DASH_coldKey').' Saved Successfully']);
		}
		
		if(!is_null($request->input('BTG_coldKey'))){
			if(env('DEMO',true)){
				return response()->json(['URL'=>route('admin.home'),'status' => 'ERROR','message' => 'Disabled in DEMO MODE. Swapping cold keys will invalidate any address already created.']);
			}
			\Setting::set('BTG_coldKey', $request->input('BTG_coldKey'));
			\Setting::save();
			return response()->json(['URL'=>route('admin.home'),'status' => 'SUCCESS','message' => trans('admin.BTG_coldKey').' Saved Successfully']);
		}
		
		
		if(!is_null($request->input('BCH_coldKey'))){
			if(env('DEMO',true)){
				return response()->json(['URL'=>route('admin.home'),'status' => 'ERROR','message' => 'Disabled in DEMO MODE. Swapping cold keys will invalidate any address already created.']);
			}
			\Setting::set('BCH_coldKey', $request->input('BCH_coldKey'));
			\Setting::save();
			return response()->json(['URL'=>route('admin.home'),'status' => 'SUCCESS','message' => trans('admin.BCH_coldKey').' Saved Successfully']);
		}
		
		if(!is_null($request->input('BTC_coldKey'))){
			if(env('DEMO',true)){
				return response()->json(['URL'=>route('admin.home'),'status' => 'ERROR','message' => 'Disabled in DEMO MODE. Swapping cold keys will invalidate any address already created.']);
			}
			\Setting::set('BTC_coldKey', $request->input('BTC_coldKey'));
			\Setting::save();
			return response()->json(['URL'=>route('admin.home'),'status' => 'SUCCESS','message' => trans('admin.BTC_coldKey').' Saved Successfully']);
		}
		
		if(!is_null($request->input('APP_MODE'))){
			if($request->input('APP_MODE')=='ICO'){
				$token = \App\Models\Token::symbol(setting('siteToken','ETH'))->LiveNow()->first();
				if(empty($token))
				return response()->json(['status' => 'ERROR','message' => trans('admin.APP_MODE').' Cant Be set to ICO. Your Site Token is NOT under ICO NOW!']);
			}
			\Setting::set('APP_MODE', $request->input('APP_MODE'));
			\Setting::save();
			return response()->json(['URL'=>route('admin.home'),'status' => 'SUCCESS','message' => trans('admin.APP_MODE').' Saved Successfully']);
		}
		
		
		if(!is_null($request->input('make_coldKeys'))){
			
			$priv = $request->input('privatekey');
			$xpriv = empty($priv)?NULL:$priv;
			$coldKeys = $this->makeColdKey($xpriv);
			
			$resp ='###YOUR COLD KEY DATA. Please Store ###'.PHP_EOL;
			$resp .='##MNEMONIC:'.PHP_EOL;
			$resp .=$coldKeys->HD->getMnemonic().PHP_EOL;
			$resp .='##PASSWORD:'.PHP_EOL;
			$resp .=$coldKeys->HD->getPassword().PHP_EOL;
			$resp .='##MASTER PRIVATE KEY:'.PHP_EOL;
			$resp .= $coldKeys->HD->getMasterXpriv().PHP_EOL;
			$resp .='##BITCOIN PUBLIC KEY at :'.$coldKeys->btc->bip44.PHP_EOL;
			$resp .= $coldKeys->btc->getXpub().PHP_EOL;
			$resp .='##LITECOIN PUBLIC KEY at :'.$coldKeys->ltc->bip44.PHP_EOL;
			$resp .= $coldKeys->ltc->getXpub().PHP_EOL;
			$resp .='##ZCASH PUBLIC KEY at :'.$coldKeys->zec->bip44.PHP_EOL;
			$resp .= $coldKeys->zec->getXpub().PHP_EOL;
			$resp .='##DASH PUBLIC KEY at :'.$coldKeys->dash->bip44.PHP_EOL;
			$resp .= $coldKeys->dash->getXpub().PHP_EOL;
			$resp .='##BITCOINGOLD PUBLIC KEY at :'.$coldKeys->btg->bip44.PHP_EOL;
			$resp .= $coldKeys->btg->getXpub().PHP_EOL;
			$resp .='##BITCOINCASH PUBLIC KEY at :'.$coldKeys->bch->bip44.PHP_EOL;
			$resp .= $coldKeys->bch->getXpub().PHP_EOL;
			if(env('DEMO',true)){
				return response()->json(['filename'=>'generatedcoldkeys','file'=>$resp,'status' => 'SUCCESS','message' => 'Download started<br> Saving Disabled in DEMO MODE. Swapping cold keys will invalidate any address already created.']);
			}
			\Setting::set('BTC_coldKey',$coldKeys->btc->getXpub());
			\Setting::set('LTC_coldKey',$coldKeys->ltc->getXpub());
			\Setting::set('ZEC_coldKey',$coldKeys->zec->getXpub());
			\Setting::set('DASH_coldKey',$coldKeys->dash->getXpub());
			\Setting::set('BTG_coldKey',$coldKeys->btg->getXpub());
			\Setting::set('BCH_coldKey',$coldKeys->bch->getXpub());
			\Setting::set('BTC_coldKey',$coldKeys->btc->getXpub());
			\Setting::set('LTC_coldKey',$coldKeys->ltc->getXpub());
			\Setting::set('ZEC_coldKey',$coldKeys->zec->getXpub());
			\Setting::set('BTCTESTNET_coldKey',$coldKeys->btcTestnet->getXpub());
			\Setting::set('BTGTESTNET_coldKey',$coldKeys->btgTestnet->getXpub());
			\Setting::set('BCHTESTNET_coldKey',$coldKeys->bchTestnet->getXpub());
			\Setting::save();
			return response()->json(['URL'=>route('admin.home'),'filename'=>'generatedcoldkeys','file'=>$resp,'status' => 'SUCCESS','message' => 'Backup Generated successfully. Download Has started']);
			
		}
		
		return response()->json(['status' => 'ERROR','message' =>'Nothing to Save']);
		
    }

}
