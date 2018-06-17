<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Scopes\ActiveScope;

class Account extends Model
{
	
	
    use SoftDeletes;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'accounts';

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];
	
	protected $hidden = [
        'account_id',
		'deleted_at',
		'user_id',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
		'account',
		'api_key',
		'balance',
		'xpriv',
		'xpub',
		'mnemonic',
		'cypher',
		'path',
		'active',
    ];

    

    protected $dates = [
        'deleted_at',
    ];
	
	protected static function boot()
    {
        parent::boot();
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
	
	public function transactions()
    {
        return $this->hasMany(\App\Models\Transaction::class, 'account_id');
    }
	public function wallets()
    {
        return $this->hasMany(\App\Models\Wallet::class, 'account_id');
    }
	public function icopurchases()
    {
        return $this->hasMany(\App\Models\Icosale::class, 'account_id');
    }
	
	public function orders()
    {
        return $this->hasMany(\App\Models\Order::class, 'account_id');
    }
	
    

   
}
