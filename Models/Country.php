<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Country extends Model
{
	
    use SoftDeletes;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'countries';

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
		'countries_name',
		'countries_iso',
		'currency',
		'currency_name',
		'exchange_rate',
		'symbol',
		'decimal_digits',
		'rounding'
	];


           
    

    protected $dates = [
        'deleted_at',
    ];

	public function scopeCurrency($query, $currency)
    {
        return $query->where('currency',$currency);
    }
	public function scopeSite($query)
    {
        return $query->where('currency',setting('siteCurrency','USD'));
    }
	public function getDecimalsAttribute()
    {
        return $this->decimal_digits;
    }

    public function orders()
    {
		return $this->morphMany(\App\Models\Order::class, 'token');
    }
	
	public function getGatewaysAttribute()
    {
		$config = collect(config('gateways.gates'));
		return $config->filter(function($gate,$key){
			return isset($gate['currencies'])&&in_array($this->symbol,$gate['currencies']);
		});
			
    }
	public function getCollectGatewaysAttribute()
    {
		$config = collect(config('gateways.gates'));
		return $config->filter(function($gate,$key){
			return isset($gate['currencies'])&&in_array($this->symbol,$gate['currencies'])&&in_array('collect',$gate['functions']);
		});
			
    }
	public function getSendGatewaysAttribute()
    {
		$config = collect(config('gateways.gates'));
		return $config->filter(function($gate,$key){
			return isset($gate['currencies'])&&in_array($this->symbol,$gate['currencies'])&&in_array('send',$gate['functions']);
		});
			
    }
}
