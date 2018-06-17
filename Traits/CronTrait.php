<?php
namespace App\Traits;
use App\Models\Country;
use App\Models\Token;

Trait  CronTrait {
	
  public function request($url, $req, $type = 'GET'){
		$client = new \GuzzleHttp\Client();
		$request = $type =='GET'?'query':'form_params';
		try {
			$response = $client->request($type, $url, [
				$request => $req
			]);
		} catch (\GuzzleHttp\Exception\TransferException $e) {
			$err =" Client Error > ". \GuzzleHttp\Psr7\str($e->getRequest());
			if ($e->hasResponse()) {
				$err.=" ". \GuzzleHttp\Psr7\str($e->getResponse());
			}
			throw new \Exception ($err);
		}
		$json = json_decode($response->getBody());
		echo $response->getBody();
        if (isset($json->error)) {
            throw new \Exception("Request API error: {$json}");
        }
        return $json;
	}
	

	public function openExchange() {
		$api = 'https://openexchangerates.org/api/latest.json';
		$key = setting('openExchangeRatesApiKey', false);
		if ($key) {
			$json = $this->request($api,['app_id'=>$key]);
			if (isset($json->rates)) {
				foreach ($json->rates as $symbol => $rate) {
					$currency = \App\Models\Country::currency($symbol)->first();
					if(!$currency)continue;
					$currency->exchange_rate = $rate;
					$currency->save();
				}
			}
		}
	}
	
	
	public function cryptoCompareRates() {
		$api ='https://min-api.cryptocompare.com/data/pricemulti';
		$rates = \App\Models\Rate::where('autoupdate', 1)->get();
		foreach ($rates->chunk(60) as $rate_chunk ) {
			$json = $this->request($api,[
				'fsyms'=>$rate_chunk->implode('fromsym', ','),
				'tsyms'=>$rate_chunk->implode('tosym', ',') 
			]);
			foreach ($rates as $rate ) {
					if(isset($json->{$rate->fromsym}->{$rate->tosym})){
						$rate->rate = $json->{$rate->fromsym}->{$rate->tosym};
						$rate->save();
					}
					
				} 
		}
	}
	
	
 
	public function cryptoCompare() {
		$api ='https://min-api.cryptocompare.com/data/pricemultifull';
		$tokens = \App\Models\Token::all();
		$siteCurrency = setting('siteCurrency','USD');
		foreach ($tokens->chunk(60) as $token_chunk ) {
			$json = $this->request($api,[
				'fsyms'=>$token_chunk->implode('symbol', ','),
				'tsyms'=>$siteCurrency 
			]);
			if (!isset($json->Response) || $json->Response != 'Error') {
				foreach ($json->RAW as $symbol => $crypto) {
					$token = \App\Models\Token::symbol($symbol)->first();
					if(!$token)continue;
					$token->price = $crypto->$siteCurrency->PRICE;
					$token->change  = $crypto->$siteCurrency->CHANGE24HOUR;
					$token->change_pct = $crypto->$siteCurrency->CHANGEPCT24HOUR;
					$token->open = $crypto->$siteCurrency->OPEN24HOUR;
					$token->low = $crypto->$siteCurrency->LOW24HOUR;
					$token->high = $crypto->$siteCurrency->HIGH24HOUR;
					$token->supply = $crypto->$siteCurrency->SUPPLY;
					$token->market_cap = $crypto->$siteCurrency->MKTCAP;
					$token->volume = $crypto->$siteCurrency->VOLUME24HOUR;
					$token->volume_ccy = $crypto->$siteCurrency->VOLUME24HOURTO;
					$token->save();
				}
			} 
		}
	}
}