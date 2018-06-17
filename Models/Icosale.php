<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Icosale extends Model
{
	
    use SoftDeletes;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'icosales';

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
		'token_id',
		'account_id',
		'ether',
		'amount',
		'token_txhash',
		'ether_txhash'
    ];
   
    

    protected $dates = [
        'deleted_at',
    ];

    /**
     * Build account Relationships.
     *
     * @var array
     */
   
   
    public function token()
    {
        return $this->belongsTo(\App\Models\Token::class);
    }
	public function account()
    {
        return $this->belongsTo(\App\Models\Account::class);
    }
	
   
}
