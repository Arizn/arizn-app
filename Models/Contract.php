<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Contract extends Model
{
	
    use SoftDeletes;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'contracts';

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
		'name',
		'buy_tokens_function',
		'abi',
		'mainsale_abi',
		'deploy_price',
		'bin',
		'contract',
		'description',
    ];
   
    

    protected $dates = [
        'deleted_at',
    ];

    /**
     * Build account Relationships.
     *
     * @var array
     */
   
   
    public function tokens()
    {
        return $this->hasMany(\App\Models\Token::class);
    }
	
	
	public function abiInputs($function){
		$data= empty($this->mainsale_abi)?json_decode($this->abi):json_decode($this->mainsale_abi);
		foreach($data as $abi){
			if($abi->type == 'function'&& $abi->name == $function){ 
				$inputs = array_map(function($input){
					$type = 'text';
					if(stripos($input->type,'uint') !== false)
					$type = 'number';
					$class = "";
					if(in_array($input->name,['_start','_end','_startTime','_endTime'])){
						$type = 'text';
						$class .='datetime';
					}
					
					$place = isset($placeHolders[$input->name])?$placeHolders[$input->name]:"Required";
					return  '<div class="item form-group">
								<label class="control-label col-md-3 col-sm-3 col-xs-12" for="'.$this->id.'_'.$input->name.'">'.ucfirst(str_replace('_',' ', $input->name)).'<span class="required">*</span>
								</label>
								<div class="col-md-9 col-sm-9 col-xs-12">
								  <input placeholder="'.$place.'" type="'.$type.'" id="'.$this->id.'_'.$input->name.'" name="contruction_'.$this->id.'['.$input->name.']" class="form-control col-md-7 col-xs-12 '.$class.'"> 
								</div>
							  </div>';
				},$abi->inputs);
				return implode('',$inputs);
			}
		}
		
	}
	
	
	public function getConstructionAttribute(){
		$price = is_null($this->buy_tokens_function)?"":'<div class="item form-group">
								<label style="color:white; padding-bottom: 8px;" class="label-danger control-label col-md-3 col-sm-3 col-xs-12" for="price">WARNING!!!</label>
								<div class="col-md-9 col-sm-9 col-xs-12">
								  <div style="height:120px" class="form-control col-md-7 col-xs-12 ">
								  In Order to failitate the ICO On this contract, your account password is required and will be stored online during the ICO period. DONOT HOLD LARGE AMOUNTS OF ETHER IN THIS ACCOUNT!!!
								  </div>
								</div>
							  </div>
							  <div class="item form-group">
								<label class="control-label col-md-3 col-sm-3 col-xs-12" for="price_'.$this->id.'">ICO Rate</label>
								<div class="col-md-9 col-sm-9 col-xs-12">
								  <input placeholder="Number of Tokens in 1 ETH" type="number" id="price_'.$this->id.'" name="rate_'.$this->id.'" class="form-control col-md-7 col-xs-12 ">
								</div>
							  </div><div class="item form-group">
								<label class="control-label col-md-3 col-sm-3 col-xs-12" for="ico_address_'.$this->id.'">Eth Recieving Address</label>
								<div class="col-md-9 col-sm-9 col-xs-12">
								  <input placeholder="Address to recieve Eth payments" type="text" id="ico_address_'.$this->id.'" name="ico_address_'.$this->id.'" class="form-control col-md-7 col-xs-12 ">
								</div>
							  </div>';
		$data= empty($this->mainsale_abi)?json_decode($this->abi):json_decode($this->mainsale_abi);
		$placeHolders = [ 
			'_name' =>"Token Name Eg CryptoTax", 
			'_symbol' =>"Ticker Symbol Eg HTD",
			'_decimals' =>"Decimal Units eg 18",
			'_initalsupply' =>"How Many Tokens To create",
			'_endtime' =>"Numbers of days of the ICO",
			'_rate' =>"Number of Tokens Per 1 ETH",
			'_cap' =>"Maximum Number of Tokens",
		];
		foreach($data as $abi){
			if($abi->type == 'constructor'){ 
				$inputs = array_map(function($input)use($placeHolders){
					$type = 'text';
					if(stripos($input->type,'uint') !== false)
					$type = 'number';
					$class = "";
					if(in_array($input->name,['_start','_end','_startTime','_endTime'])){
						$type = 'text';
						$class .=' datetime';
					}
					$place = isset($placeHolders[$input->name])?$placeHolders[$input->name]:"Required";
					return  '<div class="item form-group">
								<label class="control-label col-md-3 col-sm-3 col-xs-12" for="'.$this->id.'_'.$input->name.'">'.ucfirst(str_replace('_',' ', $input->name)).'<span class="required">*</span>
								</label>
								<div class="col-md-9 col-sm-9 col-xs-12">
								 <input placeholder="'.$place.'" type="'.$type.'" id="'.$this->id.'_'.$input->name.'" name="contruction_'.$this->id.'['.$input->name.']" class="form-control col-md-7 col-xs-12 '.$class.'">
								</div>
							  </div>';
				},$abi->inputs);
				//rate/price
				$inputs[] = $price;
				return implode('',$inputs);
			}
		}
		return null;
	}
   
}
