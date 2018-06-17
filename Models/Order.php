<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\OrderTrait;
use App\Models\Scopes\ActiveScope;


class Order extends Model
{
	
	
    use SoftDeletes, OrderTrait;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'orders';

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];
	
	protected $visible = [
		'status',
        'address',
		'symbol',
        'amount',
		'reference',
		'item_url',
		'item_data',
		'expires_at',
		'created_at',
		'transactions'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'address',
        'account_id',
		'symbol',
        'amount',
        'item',
		'item_type',
		'index',
		'reference',
		'item_url',
		'item_data',
    ];

    

    protected $dates = [
        'deleted_at',
		'expires_at',
    ];
	/**
	 * The attributes that should be casted to native types.
	 *
	 * @var array
	 */
	protected $casts = [
		'item_data' => 'array',
	];
	
	protected static function boot()
    {
        parent::boot();
       // static::addGlobalScope(new ActiveScope());
    }

    /**
     * Build account Relationships.
     *
     * @var array
     */
    public function account()
    {
        return $this->belongsTo(\App\Models\Account::class, 'account_id');
    }
	
	
	
	public function counter()
    {
        return $this->hasOne(\App\Models\Order::class, 'counter_id');//->withoutGlobalScope(ActiveScope::class);
    }
	
	public function transactions()
    {
        return $this->hasMany(\App\Models\Transaction::class, 'order_id');
    }
	
    // User 

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class , 'user_id');
    }
	
	 public function token()
    {
		
        return $this->morphTo();
    }
	
	
	
	public function scopeCompleted($query){
		return $query->where('active',0);
	}
	
	
	public function setIsPaidAttribute(){
		return $this->active == 0;
	}
	
	
	

   
}
