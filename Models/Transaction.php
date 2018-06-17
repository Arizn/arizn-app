<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Transaction extends Model
{
	
    use SoftDeletes;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'transactions';

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];
	
	protected $visible =  [
        'to_address',
        'from_address',
        'confirmations',
		'user',
		'status',
		'type',
        'amount',
        'tx_hash'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array 
     */
    protected $fillable = [
        'to_address',
        'from_address',
        'account_id',
		'user_id',
        'token_id',
		'order_id',
        'confirmations',
		'blockhieght',
        'type',
		'status',
        'amount',
        'tx_hash',
		'gas_limit',
		'nonce',
		'description',
		'gas_price',
		
    ];

    

    protected $dates = [
        'deleted_at',
    ];

    /**
     * Build account Relationships.
     *
     * @var array
     */
    public function account()
    {
        return $this->belongsTo(\App\Models\Account::class, 'account_id');
    }
	
	/**
     * Build token Relationships.
     *
     * @var array
     */
    public function token()
    {
        return $this->morphTo();
    }
	
	
	
	public function wallet()
    {
        return $this->belongsTo(\App\Models\Wallet::class, 'token_id');
    }
	
	//
	public function order()
    {
        return $this->belongsTo(\App\Models\Order::class, 'order_id');
    }
	
    // User 

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class , 'user_id');
    }
	
	 public function getConfirmsAttribute()
    {
		$last = \App\Modesl\Last::where('rid',$this->symbol)->first();
		if(!empty($this->blockheight)) return $this->confirmations;
		return $last->end_block - $this->blockheight ;
        return ;
    }
	

   
}
