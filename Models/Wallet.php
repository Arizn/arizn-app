<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Wallet extends Model
{
	
	
    use SoftDeletes;
	use \App\Traits\BitcoinTrait;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'wallets';

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];
	
	protected $hidden = [
		'deleted_at',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
		'account_id',
		'token_id',
		'token_type',
		'balance'
    ];

    

    protected $dates = [
        'deleted_at',
    ];
	
	
    /**
     * Build account Relationships.
     *
     * @var array
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
	
	public function transactions()
    {
        return $this->hasMany(\App\Models\Transaction::class, 'wallet_id');
    }
	public function addresses()
    {
        return $this->hasMany(\App\Models\Address::class, 'wallet_id');
    }
	
	public function token()
    {
        return $this->belongsTo(\App\Models\Token::class, 'token_id');
    }
	
	public function account()
    {
        return $this->belongsTo(\App\Models\Account::class, 'account_id');
    }
	 public function scopeOfToken($query, $tk_id)
    {
        return $query->where('token_id',$tk_id);
    }
	
	 public function scopeOfAccount($query, $acc_id)
    {
        return $query->where('account_id',$acc_id);
    }
	
	public function getFreeAddressAttribute(){
		
		$address = \App\Models\Address::where([['type','=','external'],['wallet_id','=',$this->id],['active','=',1]])->first();
		if(empty($address)){
			$address = $this->coin_deriveAddress($this);
		}
		return $address->address;

	}
    

   
}
