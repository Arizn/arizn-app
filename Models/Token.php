<?php

namespace App\Models;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
//use App\Models\Scopes\VerifiedScope;
use App\Models\Scopes\ActiveScope;
class Token extends Model
{
	
    use SoftDeletes;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'tokens';

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
		'user_id',
		'name',
		'slug',
		'contract_address',
		'contract_ABI_array',
		'contract_Bin',
		'ico_start',
		'ico_ends',
		'token_price',
		'active',
		'symbol',
		'decimals',
		'logo',
		'price',
		'change',
		'change_pct',
		'open',
		'low', 
		'high',
		'supply',
		'total_supply',
		'market_cap',
		'volume',
		'volume_ccy',
		'last_updated',
		'sale_active',
		'ico_active',
		'wallet_active',
		'mining_proof',
		'website',
		'twitter',
		'facebook',
		'description',
		'features',
		'technology',
		'ico_pass',
		'ico_address',
    ];


           
    

    protected $dates = [
        'deleted_at',
		'ico_start',
		'ico_ends'
    ];
	
	
	protected static function boot()
    {
        parent::boot();
		//Token::observe(TokenObserver::class);
        //static::addGlobalScope(new VerifiedScope());
        static::addGlobalScope(new ActiveScope());
		
    }

    /**
     * Build account Relationships.
     *
     * @var array
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
	
	public function accounts()
    {
        return $this->hasMany(\App\Models\Account::class,'token_id');
    }
	
	public function icosales()
    {
        return $this->hasMany(\App\Models\Icosale::class,'token_id');
    }
	
	public function account()
    {
        return $this->belongsTo(\App\Models\Account::class,'account_id');
    }
	
	public function contract()
    {
        return $this->belongsTo(\App\Models\Contract::class, 'contract_id');
    }
	
	public function wallets()
    {
        return $this->hasMany(\App\Models\Wallet::class);
    }
	
	
	public function orders()
    {
		return $this->morphMany(\App\Models\Order::class, 'token');
    }
	
	
	
	
    public function scopeSlugin($query, $slug)
    {
        return $query->where('slug',$slug);
    }
	
	 public function scopeSymbol($query, $symbol)
    {
        return $query->where('symbol',$symbol);
    }
	

    

    public function scopeByOrder($query, $type)
    {
        return $query->latest($type);
    }
	

    /**
     * Get entries by type
     *
     * @param $type
     * @return mixed
     */

    public function scopeByType($query, $type)
    {
        
		switch($type):
			case 'ico':
			return $query->where('ico_active', 1);
			case 'sale':
			return $query->where('sale_active', 1);
			case 'all':
			return $query;
			case 'wallet':
			return $query->where('wallet_active', 1);
		endswitch;
        
    }
	
	 public function scopeApprove($query, $type)
    {
		if($type=='no'){
			self::withoutGlobalScopes();
			return $query->where('active', 0);
		}
        return $query;
    }
	
	
	public function scopeInactive($query)
	{
		return $query->where('active',3);
        
    }
	
	public function scopeLiveNow($query){
		return $query->where('ico_active', 1)->where('ico_start', '<=',  Carbon::now())->where('ico_ends', '>=', Carbon::now());

	}
	
	public function scopeIsToken($query){
		return $query->where('ico_ends', '>=', Carbon::now())->orWhereNull('ico_ends');

	}
	
	public function getIsIcoAttribute(){
		return $this->ico_active &&  Carbon::now()->lessThan(new Carbon($this->ico_ends));
	}
	public function getChainIdAttribute(){
		$chains = ['olympic'=>0,'frontier'=>1,'mainnet'=>1,'homestead'=>1,'metropolis'=>1,'classic'=>1,'expanse'=>1,'morden'=>2,'ropsten'=>3,'rinkeby'=>4,'kovan'=>42];
		$set = setting('ETHEREUMNETWORK','mainnet');
		return isset($chains[$set])?$chains[$set]:1;
	}
	public function getIsLiveAttribute(){
		return $this->ico_active &&  Carbon::now()->lessThan(new Carbon($this->ico_ends)) &&  Carbon::now()->greaterThan(new Carbon($this->ico_start));
	}
	
	public function getCountryRateAttribute(){
		$symbol = setting('siteCurrency','USD');
		return $symbol.$this->price;
	}
	
	public function getIsAdminAttribute(){
		return $this->user_id == auth()->user()->id&&auth()->user()->account->id==$this->account_id;
	}
	
	public function getIsBuyableAttribute(){
		return $this->contract()->count() < 1 || ($this->contract()->count() && empty(trim($this->contract->buy_tokens_function))); 
	}
	
	
	public function setIco_passwordAttribute()
    {
        return Crypt::encrypt($this->ico_password);
    }

    public function getIco_passwordAttribute()
    {
        return Crypt::decrypt($this->ico_password);
    }
	
	public function getBuyFunctionInputsAttribute(){
		$contract = $this->contract;
		$data= empty($contract->mainsale_abi)?json_decode($contract->abi):json_decode($contract->mainsale_abi);
		$inputs =[];
		$cnt = empty($contract->mainsale_abi)?'abi':'mainsale';
		foreach($data as $abi){
			if($abi->type == 'function'&& $abi->name == $contract->buy_tokens_function){ 
				$inputs = array_map(function($input){
					
					if($input->type == 'address'){
						return  '<input type="hidden"  type="hidden" value="'.auth()->user()->account->account.'"  name="contruction['.$input->name.']">';
					} 
						$name = 'Amount';
						$id = 'amount';
						$place = 'Amount of Tokens';
						$class = '';
						$type = 'text';
						if(stripos($input->type,'uint') !== false)
						$type = 'number';
						return  '<input value="'.$this->token_price.'" placeholder="'.$place.'" v type="'.$type.'" id="'.$id.'" name="contruction['.$input->name.']" class="form-control col-md-7 col-xs-12 '.$class.'">';
					
 
 				},$abi->inputs);
				
				
			}
		}
		$inputs[] ='<input type="hidden"   value="'.$cnt.'"  name="contract">';
		return implode('',$inputs);
	}
	
	
	public function getGatewaysAttribute()
    {
       return collect([[
				'name'=>$this->name,
				'logo'=>$this->logo, 
				'class'=>'\App\Logic\Gateways\Blockchain',
				'currency' => $this->symbol,
				'functions'=>['send','collect'],
			]]);
	
    }
	
	
	
	
	

   
}
