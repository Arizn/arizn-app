<?php

namespace App\Traits;

use App\Logic\Activation\ActivationRepository;
use App\Models\User;
use App\Models\Account;
trait WalletTrait

{
	use BitcoinTrait;
	
		public function isOffNet($token){
			if(!isset($token->net)||empty($token->net)) return false;
			$chains = ['olympic'=>0,'frontier'=>1,'mainnet'=>1,'homestead'=>1,'metropolis'=>1,'classic'=>1,'expanse'=>1,'morden'=>2,'ropsten'=>3,'rinkeby'=>4,'kovan'=>42];
			$set = setting('ETHEREUMNETWORK','mainnet');
			$chain = isset($chains[$set])?$chains[$set]:1;
			$net = isset($chains[$token->net])?$chains[$token->net]:1;
			return $net != $chain;
		}
	 	
		private function web3(){
			$infuraToken =  env('INFURATOKEN', setting('INFURATOKEN',''));
        	$etherscanToken  = env('ETHERSCANTOKEN', setting('ETHERSCANTOKEN',''));
		 	$parityIp  =  env('PARITYIP', setting('PARITYIP', '127.0.0.1:8545'));
        	$ethereumNetwork   = env('ETHEREUMNETWORK', setting('ETHEREUMNETWORK','mainnet'));
        	$ethereumProvider  = env('ETHEREUMPROVIDER', setting('ETHEREUMPROVIDER','infura'));
			
			if($ethereumProvider == 'infura'){
				if(empty($infuraToken))throw new \Exception('Please Add infura Token in .env File');
				$provider = new \phpEther\Web3\Providers\Infura($infuraToken , $ethereumNetwork); 
				return new \phpEther\Web3($provider);
			}
			if($ethereumProvider == 'etherscan'){
				if(empty($etherscanToken))throw new \Exception('Please Add etherscanToken in .env File');
				$provider = new \phpEther\Web3\Providers\Etherscan($etherscanToken , $ethereumNetwork); 
				return new \phpEther\Web3($provider);
			}
			if($ethereumProvider == 'parity'){
				if(empty($parityIp))throw new \Exception('Please Add  Geth IP address in .env File');
				$provider = new \phpEther\Web3\Providers\Geth($parityIp , $ethereumNetwork); 
				return new \phpEther\Web3($provider);
			}
			
			throw new \Exception('No provider Selected');
		}
	
		public function tx_link($tx_id){
			$ethereumNetwork   = env('ETHEREUMNETWORK', setting('ETHEREUMNETWORK','mainnet'));
			$api = in_array($ethereumNetwork,['frontier', 'homestead', 'metropolis','mainnet'])?'':$ethereumNetwork.'.';
			return "https://{$api}etherscan.io/tx/".$tx_id; 
		}
		
		public function address_link($address){
			$ethereumNetwork   = env('ETHEREUMNETWORK', setting('ETHEREUMNETWORK','mainnet'));
			$api = in_array($ethereumNetwork,['frontier', 'homestead', 'metropolis','mainnet'])?'':$ethereumNetwork.'.';
			return "https://{$api}etherscan.io/address/".$address;
		}
		
		protected function convertIntegers($construction){
			foreach($construction as $k => $v){
				if( 
					stripos(strtolower($k),'start')!==false||
					stripos(strtolower($k),'end')!==false||
					stripos(strtolower($k),'time')!==false
				)
				{
					$construction[$k] = \Carbon\Carbon::parse($construction[$k])->timestamp;
				}
				
				if(
					stripos(strtolower($k),'teamBonus')!==false||
					stripos(strtolower($k),'cap')!==false||
					stripos(strtolower($k),'price')!==false||
					stripos(strtolower($k),'rate')!==false||
					stripos(strtolower($k),'amount')!==false||
					stripos(strtolower($k),'value')!==false||
					stripos(strtolower($k),'bid')!==false||
					stripos(strtolower($k),'asksize')!==false||
					stripos(strtolower($k),'supply')!==false
				)
				{
					$construction[$k] = $this->web3()->toWei($construction[$k]);
				}
			}
			return $construction;
		}
	
    
		
		protected function decodeInteger($method, $val){
			if(
				stripos(strtolower($method),'volume')!==false||
				stripos(strtolower($method),'supply')!==false||
				stripos(strtolower($method),'balance')!==false||
				stripos(strtolower($method),'tokens')!==false
				
			)
			{
				return $this->web3()->fromWei($val);
			}
			
			return $val;
			
		}
		
		
		protected function resolve($contract,\App\Models\Token $token,$func,$construction=[],$password=NULL,$eth=0,$account = NULL)
		{
			$account = $account?$account:auth()->user()->account;
			$web3 =  $this->web3();
			$construct = empty($construction)?[]:$this->convertIntegers($construction);
			$abi = $contract == "mainsale"?$token->contract->mainsale_abi:$token->contract_ABI_array;
			$address = $contract == "mainsale"?$token->mainsale_address:$token->contract_address;
			try{
				$result = $this->Query($address, $abi,$func, $token, $account, $construct, $password ,$eth);
			}catch(Exception $e){
				throw $e;
			}
			return $this->get_response($func,$result);
		}
		
		private function get_response($func, $res){
		if(is_numeric($res)){
			$nbr =  (string)$this->decodeInteger($func,$res);
			return strpos($nbr,'.')!==false ? rtrim(rtrim($nbr,'0'),'.') : $nbr;
		}
		if(!is_array($res))return $res;
		$result = "";
		foreach($res as $k => $v )
		{
			if(!is_array($v))
			$v  = $this->get_response($func,$v);
			$result.= " ".$k .' = '.$v.'<br>';
		}
		return $result;
	}
		
		
		public function unlockAccount($account,$password){
			$protected_key =\Defuse\Crypto\KeyProtectedByPassword::loadFromAsciiSafeString($account->cypher);
		    $key  = $protected_key->unlockKey($password);
			$master_xpriv = \Defuse\Crypto\Crypto::decrypt($account->xpriv,$key);
			$HD = new \phpEther\HD();
			$index = $account->idx?$account->idx:0;
			return $HD->masterSeed($master_xpriv)->getAccount($index);
		}
		
		public function unlocPrivateKey($account,$password){
			$protected_key =\Defuse\Crypto\KeyProtectedByPassword::loadFromAsciiSafeString($account->cypher);
		    $key  = $protected_key->unlockKey($password);
			$master_xpriv = \Defuse\Crypto\Crypto::decrypt($account->xpriv,$key);
			$HD = new \phpEther\HD();
			return [ \Defuse\Crypto\Crypto::decrypt($account->mnemonic,$key),$HD->masterSeed($master_xpriv)];
			
		}
		 
		
		public function deploy(\App\Models\Account $from, string $password, string $contractABI,string $contractBIN, array $construction){
			
			$web3 = $this->web3();
			$account = $this->unlockAccount($from,$password);
			$last = $from->transactions()->orderBy('nonce','dec')->first();
			$nonce = empty($last)?0:$last->nonce+1; // last tx in not paid
			$tx = new \phpEther\Transaction($account, NULL, 0 , NULL ,$nonce); 
			$contract = $web3->eth->contract($contractABI)->deploy($contractBIN , $tx);
			// send to the blockchain by calling the contructor
			$res = $contract->constructor($construction);
			$token = \App\Models\Token::where('symbol','ETH')->first();
			if($res instanceof \phpEther\Transaction){
				$tx = $res->getTx();
				$transaction = new \App\Models\Transaction(); 
				$transaction->confirmations = 0;
				$transaction->from_address  = $from->account;
				$transaction->to_address  = "SMART_CONTRACT";
				$transaction->type ='debit';
				$transaction->account_id =$from->id;
				$transaction->user_id =$from->user_id;
				$transaction->token_id = $token->id;
				$transaction->token_type = '\App\Models\Token';
				$transaction->amount = $tx->value;
				$transaction->tx_hash = $tx->hash;
				$transaction->description = "You Deployed a Smart Contract";
				$transaction->nonce = $tx->nonce ;
				$transaction->gas_price = $web3->fromWei($tx->gasPrice) ;
				$transaction->gas_limit = $web3->fromWei($tx->gasLimit) ;
				$transaction->save();
				return $transaction->tx_hash;
			}
			return  $res; 
		}
		
		
		public function Query($address, $abi, string $func, \App\Models\Token $token, \App\Models\Account $from,  array $construct = [] ,$password = NULL, $eth=0 ){
		
			$web3 = $this->web3();
			$ether = NULL;
			if($this->isOffNet($token))throw new \Exception('<p class="red">INVALID NETWORK. Please contact Admin. This Token is for '.$token->net.' But Network is set to '.setting('ETHEREUMNETWORK').' </p>');
			
			$contract = $web3->eth->contract($abi)->at($address)->decode();
			if($password){
				try{
					$account = $this->unlockAccount($from,$password);
				}catch(\Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $e){
					throw new \Exception('<p class="red">INVALID PASSWORD</p> <p>'.$e->getMessage().'</p>');
				}
				
				if(empty($eth)){ 
					$ether = 0;
				}else{
					$ether = $web3->toWei($eth);
					
				}
				$last = $from->transactions()->orderBy('nonce','dec')->first();
				$nonce = empty($last)?0:$last->nonce+1; // last tx in not paid
				$tx = new \phpEther\Transaction($account, NULL, $ether , NULL ,$nonce);
				$construct[] = $tx; 
			}
			
			//dd($construct);
			$res = call_user_func_array(array($contract, $func), $construct);
			//$res = $contract->$func($construct);
			if($res instanceof \phpEther\Transaction){
				$tx = $res->getTx();
				$ethereum = \App\Models\Token::where('symbol','ETH')->first();
				$transaction = new \App\Models\Transaction(); 
				$transaction->confirmations = 0;
				$transaction->from_address  = $from->account;
				$transaction->to_address  = $token->contract_address;
				$transaction->type ='query';
				$transaction->account_id =$from->id;
				$transaction->user_id =$from->user_id;
				$transaction->token_id = $ethereum->id;
				$transaction->token_type = '\App\Models\Token';
				$transaction->amount = $eth; 
				$transaction->tx_hash = $tx->hash; 
				$transaction->description = "You Queried { $token->name } {$token->symbol}=>{$func}() Contract ";
				$transaction->nonce = $tx->nonce ;
				$transaction->gas_price = $web3->fromWei($tx->gasPrice) ;
				$transaction->gas_limit = $web3->fromWei($tx->gasLimit) ;
				$transaction->save();
				return $transaction->tx_hash;
			}
			return $res;
			
		}
		
		public function deriveAddress(\App\Models\Account $account){
			$HD = new \phpEther\HD();
			$index = $account->orders()->count()+1;
			return [$index , $HD->publicSeed($account->xpub)->getAddress($index)];
		}
	
		public function create_account( User $user , $password , $idx = 0 ){
			$GN = new \phpEther\HD();
			$HD = $GN->randomSeed($password); // get random HD Keys
			$account = $HD->getAccount($idx);
			$locked_key = \Defuse\Crypto\KeyProtectedByPassword::createRandomPasswordProtectedKey($password);
			$locked_key_encoded = $locked_key->saveToAsciiSafeString();// now in db
  			$protected_key = \Defuse\Crypto\KeyProtectedByPassword::loadFromAsciiSafeString($locked_key_encoded);
		    $key = $protected_key->unlockKey($password);
			$token = $user->createToken('Merchant-Key')->accessToken;
			$acc = $user->account()->firstOrNew([
				'account'=>$account->getAddress(),
				'api_key'=>str_random('28'),
				'user_id'=>$user->id,
				'api_key'=> $token,
				'xpub'=>$HD->getXpub(),
				'mnemonic'=>\Defuse\Crypto\Crypto::encrypt($HD->getMnemonic(), $key),
				'xpriv'=> \Defuse\Crypto\Crypto::encrypt($HD->getMasterXpriv(), $key),
				'cypher'=>$locked_key_encoded,
				]);
			$acc->save();
			return true;
		}
		
	  public function balance($address, \App\Models\Token $token = NULL){
		  if(!empty($token) && $token->family != "ethereum") return 0;
		  	$web3 = $this->web3();
		   if(is_null($token)||$token->symbol == 'ETH'){
				$res = $web3->eth->getBalance($address);
				if(empty($res->getHex()))return 0;
				return $web3->fromWei($res->getInt());
			}elseif(!empty($token->contract_address)){// token
				if($this->isOffNet($token))return 0;
				$token = $web3->eth->contract($this->abi)->at($token->contract_address);
				$res = $token->balanceOf($address);
				if(empty($res->getHex()))return 0;
				return $web3->fromWei($res->getInt());
			}
		}
	 
	 public function getERC20CoinInfo(\App\Models\Token $token)
		{
			
			if(!empty($this->abi)){// token
				$web3 = $this->web3();
				$abiArray = json_decode($this->abi, true) ;
				$token = $web3->eth->contract($this->abi)->at($token->contract_address);
				$name = $token->name()->getBinary();
				$symbol = $token->symbol()->getBinary();
				$decimals = $token->decimals()->getInt();
				
				//dd($name , $symbol, $decimals);
				if(stripos($this->abi,'totalSupply')!==false)
					$totalSupply = $token->totalSupply()->getInt();
					
				if (!$symbol) {
					throw new \Exception('This is not ERC20 coin');
				}
				if (!$name) {
					$name = $symbol;
				}
				return [$name,$symbol,$decimals,$totalSupply];
			}
		} 
		
		public function setERC20CoinInfo(\App\Models\Token $token)
		{
			$web3 = $this->web3();
			$abi = $this->abi;
			if($token->contract()->count()){
				$abi = $token->contract->abi;
				$mainsale = $token->contract->mainsale_abi;
				if(!empty($mainsale)){
					$mainsale_contract = $web3->eth->contract($mainsale)->at($token->contract_address);
					$token->mainsale_address =  $token->contract_address;
					$token->contract_address = '0x'. $mainsale_contract->token()->getHex();
					try{
						$mainsale_contract->getMethodBin('hardcap',  []);
						$tt = $mainsale_contract->hardcap();
						$total_supply = empty($tt->getHex())?0:$tt->getInt();
						$token->total_supply =$total_supply?(int)$web3->fromWei($total_supply):0 ;
					}catch (\Exception $e ){
					}
					try{
						$mainsale_contract->getMethodBin('rate',  []);
						$tr = $mainsale_contract->rate();
						$price = $token->token_price  = empty($tr->getHex())?0:$tt->getInt();
						$token->price = $token->token_price  = $price ?(int)$web3->fromWei($price):0;
					}catch (\Exception $e ){
					}
				}
			}
			$contract = $web3->eth->contract($abi)->at($token->contract_address);
			$token->name = $contract->name()->getBinary();
			$token->symbol = $contract->symbol()->getBinary();
			$token->decimals = $contract->decimals()->getInt();
			try{
				$contract->getMethodBin('hardcap',  []);
				$tt = $contract->hardcap();
				$total_supply = empty($tt->getHex())?0:$tt->getInt();
				$token->total_supply = $total_supply?(int)$web3->fromWei($total_supply):0 ;
			}catch (\Exception $e ){
			}
			try{
				$contract->getMethodBin('rate',  []);
				$tr = $contract->rate();
				$price = $token->token_price  = empty($tr->getHex())?0:$tt->getInt();
				$token->price = $token->token_price  = $price ?(int)$web3->fromWei($price):0;
			}catch (\Exception $e ){
			}
			try{
				$contract->getMethodBin('totalSupply',  []);
				$tt = $contract->totalSupply();
				$total_supply = empty($tt->getHex())?0:$tt->getInt();
				$token->total_supply = $total_supply?(int)$web3->fromWei($total_supply):0 ;
			}catch (\Exception $e ){
			}
			
			if (!$token->symbol) {
				throw new \Exception('This is not ERC20 coin');
			}
			if (!$token->name) {
				$token->name = $token->symbol;
			}
			$token->save();
			return $token;
		} 
		
		public function send($amt, $to, \App\Models\Account $from, $password, \App\Models\Token $token = NULL, $order_id = NULL) 	
		{
			if($amt > 0.01 && env('DEMO',false)){
				throw new \Exception('Max Tx in the Demo is 0.01, so Others can also Test');
			}
			$account = $this->unlockAccount($from,$password);
			$web3 =  $this->web3();
			$value = $web3->toWei($amt, 'ether');
			///dd($amt,$value);  
			if(!is_null($token)){
				$contract = $web3->eth->contract($this->abi)->at($token->contract_address);
				try{
					$res = $contract->transfer($to, $value, new \phpEther\Transaction($account));
				}catch( Exception $e ){
					throw $e;
				}
			}else{
				$tx = new \phpEther\Transaction(
					$account, 
					$to,
					(int)$value
				);
				
				try{
					$res = $tx->setWeb3($web3)->prefill()->send();
				}catch( Exception $e ){
					throw $e;
				}
			}
			if(is_null($token))
			$token = \App\Models\Token::symbol('ETH')->first();
			$tx = $res->getTx();
			$transaction = new \App\Models\Transaction();
			$transaction->confirmations = 0;
			$transaction->from_address  = $from->account;
			$transaction->to_address  = $to;
			$transaction->type ='debit';
			$transaction->order_id = $order_id;
			$transaction->account_id =$from->id;
			$transaction->user_id =$from->user_id;
			$transaction->token_id = $token->id;
			$transaction->token_type = '\App\Models\Token';
			$transaction->amount = $amt;
			$transaction->tx_hash = $tx->hash;
			$transaction->description = "You Sent {$amt} {$token->symbol}";
			$transaction->nonce = $tx->nonce ;
			$transaction->gas_price = $web3->fromWei($tx->gasPrice) ;
			$transaction->gas_limit = $web3->fromWei($tx->gasLimit) ;
			$transaction->save();
  			return $tx->hash;
		}
		
		public function refresh_hash($hash , \App\Models\Token $token =NULL){
			$web3 =  $this->web3();
			$TX = $web3->eth->getTransactionByHash($hash);
			$now = $ether->eth_blockNumber();
			$confirms  = gmp_strval( gmp_sub($now->getGmp(),$TX->blockNumber->getGmp()));
			$TX->confirmations =  $confirms;
			$TX->address = is_null($token)?NULL:$token;
			$txs = [];
			if(!empty($TX)){
				if(!is_null($token)){
					if($this->isOffNet($token))return false;
					$filter = new \phpEther\Filter($TX->blockNumber->getInt(),$now->getInt(),$TX->to,["0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef"]);
					$logs =  $web3->eth->getLogs($filter);
					$txs = array_map(function($log)use($TX,$confirms){
						$tx = new stdClass;
						list(,$tx->from, $$tx->to) = $log->topics;
						if('0x'.$log->transactionHash->getHex() != $TX->hash );
						return NULL;
						$tx->value = $log->data;
						$tx->hash =  $log->transactionHash ;
						$tx->address = $log->address;
						//$tx->blockHash = $log->blockHash ;
						$tx->blockNumber = $log->blockNumber;
						$tx->confirmations = $confirms;
						$tx->transactionIndex = $log->transactionIndex;
						return $tx;
					}, $logs);
				}
				$txs[] = $TX;
				foreach($txs as $txn){
					if(is_null($txn))continue;
				 	$this->processtx($txn ,$token);
				}
				 
			}
		}
		
		
		public function processtx($tx , \App\Models\Token $token =NULL ,\App\Models\Order $order =NULL){
	
			$web3 = $this->web3();
			if(is_null($token))
			$token = \App\Models\Token::symbol('ETH')->first();
			\DB::table('transactions')
            	->where('tx_hash', '0x'.$tx->hash->getHex())
            	->update(['confirmations'=>$tx->confirmations]);
			$event = \App\Models\Transaction::where('type','credit')->where('tx_hash','0x'.$tx->hash->getHex())->get();
			
			if($event->count() < 1){  
				$to = \App\Models\Account::where('account','0x'.$tx->to->getHex())->first();
				$symbol = $token->symbol;
				if(!empty($order))
				$to = $order->account;
				if(empty($to))return false;
			    $transaction = new \App\Models\Transaction();
				$amt = number_format((float)$web3->fromWei($tx->value->getInt()),8);
				$transaction->confirmations = isset($tx->confirmations)?$tx->confirmations: 5;
				$transaction->from_address  = '0x'.$tx->from->getHex();
				$transaction->to_address  = '0x'.$tx->to->getHex();
				$transaction->type ='credit';
				$transaction->account_id =$to->id;
				$transaction->user_id =$to->user_id;
				$transaction->token_id = $token->id;
				$transaction->token_type = '\App\Models\Token';
				$transaction->amount = $amt;
				$transaction->tx_hash = '0x'.$tx->hash->getHex();
				$transaction->description = "You Recieved {$amt}{$token->symbol}";
				$transaction->save(); 
				if(empty($order)){
					return  $transaction->id;   
				}
				$transaction->order_id = $order->id;
				$transaction->save();
				if($tx->confirmations > setting('minConf', 3) ){
					try{
						 $order->completeOrder($order);
						 return true;
					}catch(Exception $e){
						return false;
					}
				}
				return true;
 			}
			foreach($event as $txn ) { // out going or incoming Transaction
				$order = $txn->order;
				if(empty($order)) continue;
				if($txn->confirmations > setting('minConf', 3))
				$order->completeOrder($order);
			}
			return true;
		 }
		 
		 
		public function refresh_tx(\Illuminate\Support\Collection $accounts , \App\Models\Token $token = NULL)
		{
			
			
			$rid = is_null($token)?'ETH':$token->symbol;
			$web3 = $this->web3();
			$end = $web3->eth->blockNumber();
			$last = \App\Models\Last::where('rid', $rid)->first();
			$startBlockNumber =  $last->start_block;
			$endBlockNumber = $last->end_block;
			$mine = [];
		 	//$startBlockNumber = 3026917  ;
	    	//$endBlockNumber = 3026919 ;
			
			$addresses = $accounts->pluck('account')->map(function($v,$k){
								return strtolower($v);
							})->all() ;
			if(is_null($token)){
				if($this->isOffNet($token))return false;
					for($i = $startBlockNumber; $i <= $endBlockNumber;  $i++){
						$block = $web3->eth->getBlockByNumber( $i, true);
						if(!isset($block->transactions))continue;
						$found = array_filter($block->transactions,function($tx)use($addresses){
							return (in_array('0x'.$tx->from->getHex(), $addresses)||in_array('0x'.$tx->to->getHex(), $addresses));
							
						});
						$mine = array_merge($mine,$found);
					}
					
					foreach($mine as $tx){
						$tx->confirmations = gmp_strval(gmp_sub($end->getGmp(),$tx->blockNumber->getGmp()));
						$this->processtx($tx);
					}
					return true;
			}elseif(!empty(trim($token->contract_address))){
					$filter = new \phpEther\Filter($startBlockNumber,$endBlockNumber,$token->contract_address,["0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef"]);
					$logs =  $web3->eth->getLogs($filter);
					$txs = array_map(function($log)use($end,$addresses){
						$tx = new \stdClass;
						list(,$tx->from, $tx->to) = $log->topics;
						if(!in_array('0x'.$tx->to->getHex(),$addresses)&&!in_array('0x'.$tx->from->getHex(),$addresses))
						return NULL;
						$tx->value =  $log->data;
						$tx->hash = $log->transactionHash;
						$tx->address = $log->address;
						//$tx->blockHash = $log->blockHash;
						$tx->confirmations = gmp_strval(gmp_sub($end->getGmp(),$log->blockNumber->getGmp()));
						$tx->blockNumber = $log->blockNumber;
						$tx->transactionIndex = $log->transactionIndex;
						return $tx;
					}, $logs);

				foreach($txs as $result){
					if(is_null($result))continue;
					$this->processtx($result, $token);
				}
			}
		}
		
		
		public function refresh_order(\App\Models\Order $order )
		{
			if($order->transactions()->count() > 0){
				$least_confirmed = $order->transactions->pluck('confirmations')->min();
				if($least_confirmed > setting('minConf', 3)){
					if($order->completeOrder($order))return;
				}
			}
			
			$token = $order->token;
			if(!$order->token instanceof\App\Models\Token||$order->token->family=='bitfamily' ){
				return 'None Eth Token';
			}
			$rid =  $token->symbol."ORDER";
			$web3 = $this->web3();
			$end = $web3->eth->blockNumber();
			$last = \App\Models\Last::where('rid', $rid)->first();
			$startBlockNumber =  $last->start_block;
			$endBlockNumber = $last->end_block;
			$mine = [];
		 	
			$address = strtolower($order->address);
			if($token->symbol=="ETH"){
				for($i = $startBlockNumber; $i <= $endBlockNumber;  $i++){
						$block = $web3->eth->getBlockByNumber( $i, true);
						if(!isset($block->transactions))continue;
						$found = array_filter($block->transactions,function($tx)use($address){
							return (strtolower('0x'.$tx->from->getHex()) == $address||strtolower('0x'.$tx->to->getHex()) == $address);
							
						});
						$mine = array_merge($mine,$found);
					}
					
					foreach($mine as $tx){
						$tx->confirmations = gmp_strval(gmp_sub($end->getGmp(),$tx->blockNumber->getGmp()));
					
						$this->processtx($tx ,NULL,$order);
					}
					return true;
			}elseif(!empty(trim($token->contract_address))){
					if($this->isOffNet($token->net))return false;
					$filter = new \phpEther\Filter($startBlockNumber,$endBlockNumber,$token->contract_address,["0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef"]);
					$logs =  $web3->eth->getLogs($filter);
					$txs = array_map(function($log)use($end,$address){
						$tx = new \stdClass;
						list(,$tx->from, $tx->to) = $log->topics;
						if(strtolower('0x'.$tx->from->getHex()) == $address||strtolower('0x'.$tx->to->getHex()) == $address)
						return NULL;
						$tx->value =  $log->data;
						$tx->hash = $log->transactionHash;
						$tx->address = $log->address;
						$tx->confirmations = gmp_strval(gmp_sub($end->getGmp(),$log->blockNumber->getGmp()));
						$tx->blockNumber = $log->blockNumber;
						$tx->transactionIndex = $log->transactionIndex;
						return $tx;
					}, $logs);

				foreach($txs as $result){
					$this->processtx($result, $token ,$order );
				}
			}
			
		}
		

		public function cron() {
			
			$deployed = \App\Models\Token::withoutGlobalScopes()->inactive()->get();
			$accounts = \App\Models\Account::all();
			$orders = \App\Models\Order::with('token')->whereNull('counter_id')->where('status','UNPAID')->where('expires_at','>',\Carbon\Carbon::now())->get();
			foreach($orders->pluck('token')->unique('symbol') as $token ){
				$rid = is_null($token)?'ETHORDER':$token->symbol."ORDER";
				$last = \App\Models\Last::where('rid', $rid)->first();
				$last = $last?$last:new \App\Models\Last;
				if($token->family = 'ethereum'){
					$web3 = $this->web3();
					$end = $web3->eth->blockNumber();
					$endBlockNumber = $end->getInt();
					$startBlockNumber =$last? (int)$last->end_block-8:$endBlockNumber- 10;
				}elseif($token->family = 'bitfamily'){
					$api = $this->api($key);
					$endBlockNumber = $api->currentBlock()->blocks;
					$startBlockNumber =$last? (int)$last->end_block-1:$endBlockNumber- 5;
				}
				$last->rid = $rid;
				$last->start_block = $startBlockNumber ;
				$last->end_block = $endBlockNumber;
				$last->save();
			}
			foreach($orders as $order ){
				if($order->token->family = 'ethereum'){
					$response = $this->refresh_order( $order);
				}elseif($order->token->family = 'bitfamily'){
					$response = $this->coin_refresh_order( $order);
				}
			}
			
			
			//$res = $this->coin_refresh_tx($wallets);
			
			foreach ($accounts as $account){
				$balance = $this->balance($account->account);
				$account->balance = number_format((float)$balance,8);
				$account->save();
			}
			
			foreach ($deployed as $token){
				if(stripos($token->supply,'txhash')===false)
				continue;
				if($this->isOffNet($token))continue;
				list(,$txHash) = explode('_',$token->supply);
				$txR = $this->web3()->eth->getTransactionReceipt($txHash);
				try{
					if(isset($txR->contractAddress)&&!empty($txR->contractAddress->getHex())){
						$token->contract_address = '0x'.$txR->contractAddress->getHex();
						$token = $this->setERC20CoinInfo($token);
						$token->active = 1;
						$token->supply = $token->total_supply;
						$token->slug = strtolower($token->symbol);
						$token->save();
						// 
						$tokenWallet = $token->user->wallets()->ofToken($token->id)->firstOrNew([
							'user_id'=>$token->user->id,
							'account_id'=>$token->user->account->id,
							'token_id'=>$token->id,
						])->save();	
					}
				}catch(\Exception $e){
					continue;
				}
			}
			
				
			// Tokens
			$eth = \App\Models\Token::where('symbol','ETH')->first();
			$tokens = \App\Models\Token::has('wallets')->get();
			$wallets = \App\Models\Wallet ::with('token')->with('account')->get();
			foreach ($wallets as $wallet){
					if($wallet->token->family != "ethereum") continue;
					$balance = $this->balance($wallet->account->account, $wallet->token);
					$balance = substr($balance , 0, 9);
					$wallet->balance = $balance ;
					$wallet->save();
			}
			$accounts = $wallets->pluck('account')->unique('account');
			$tokens =$wallets->pluck('token')->concat([$eth])->unique('symbol');
			foreach($tokens as $token ){
				if($token->family != "ethereum") continue;
				$rid = is_null($token)?'ETH':$token->symbol;
				$web3 = $this->web3();
				$end = $web3->eth->blockNumber();
				$endBlockNumber = $end->getInt();
				$last = \App\Models\Last::where('rid', $rid)->first();
				$startBlockNumber =$last? (int)$last->end_block-4:$endBlockNumber- 10;
				$last = $last?$last:new \App\Models\Last;
				$last->rid = $rid;
				$last->start_block = $startBlockNumber ;
				$last->end_block = $endBlockNumber;
				$last->save();
				$this->refresh_tx($accounts, $token);
			}
		}
		
		public $abi ='[{"constant":true,"inputs":[],"name":"name","outputs":[{"name":"","type":"string"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"name":"_spender","type":"address"},{"name":"_amount","type":"uint256"}],"name":"approve","outputs":[{"name":"success","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[],"name":"totalSupply","outputs":[{"name":"totalSupply","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"name":"_from","type":"address"},{"name":"_to","type":"address"},{"name":"_amount","type":"uint256"}],"name":"transferFrom","outputs":[{"name":"success","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[],"name":"decimals","outputs":[{"name":"","type":"uint8"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[{"name":"_owner","type":"address"}],"name":"balanceOf","outputs":[{"name":"balance","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"owner","outputs":[{"name":"","type":"address"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":true,"inputs":[],"name":"symbol","outputs":[{"name":"","type":"string"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"name":"_to","type":"address"},{"name":"_amount","type":"uint256"}],"name":"transfer","outputs":[{"name":"success","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"constant":true,"inputs":[{"name":"_owner","type":"address"},{"name":"_spender","type":"address"}],"name":"allowance","outputs":[{"name":"remaining","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"},{"constant":false,"inputs":[{"name":"ethers","type":"uint256"}],"name":"withdrawEthers","outputs":[{"name":"ok","type":"bool"}],"payable":false,"stateMutability":"nonpayable","type":"function"},{"inputs":[{"name":"_name","type":"string"},{"name":"_symbol","type":"string"},{"name":"_decimals","type":"uint8"}],"payable":false,"stateMutability":"nonpayable","type":"constructor"},{"payable":true,"stateMutability":"payable","type":"fallback"},{"anonymous":false,"inputs":[{"indexed":true,"name":"_owner","type":"address"},{"indexed":false,"name":"_amount","type":"uint256"}],"name":"TokensCreated","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"name":"_from","type":"address"},{"indexed":true,"name":"_to","type":"address"},{"indexed":false,"name":"_value","type":"uint256"}],"name":"Transfer","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"name":"_owner","type":"address"},{"indexed":true,"name":"_spender","type":"address"},{"indexed":false,"name":"_value","type":"uint256"}],"name":"Approval","type":"event"}]';
		
   
}
