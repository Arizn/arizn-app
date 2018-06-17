<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{
	
	
    use SoftDeletes;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'addresses';

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
		'wallet_id',
		'balance',
		'address_link',
		'address',
		'symbol'
		
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
	
	
	public function token()
    {
        return $this->belongsTo(\App\Models\Token::class, 'token_id');
    }
	
	public function wallet()
    {
        return $this->belongsTo(\App\Models\Wallet::class, 'token_id');
    }
	
	public function account()
    {
        return $this->belongsTo(\App\Models\Account::class, 'account_id');
    }

   
}
