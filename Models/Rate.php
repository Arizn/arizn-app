<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\ActiveScope;
class Rate extends Model
{
	
	protected static function boot()
    {
        parent::boot();
		//Token::observe(TokenObserver::class);
        //static::addGlobalScope(new VerifiedScope());
        static::addGlobalScope(new ActiveScope());
		
    }
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'rates';

    /**
    * The database primary key value.
    *
    * @var string
    */
    protected $primaryKey = 'id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
		'src_id', 
		'dst_id', 
		'pair_id', 
		'rate', 
		'fees',
		'fromsym', 
		'tosym', 
		'src_type',
		'dst_type',
		'src_gateway',
		'dst_gateway',  
		'tosym',   
		'tosym',   
		'minimum', 
		'maximum', 
		'message'
	];

    public function src()
    {
        return  $this->morphTo();
    }
	
	public function dst()
    {
        return  $this->morphTo();
    }
	
  
	 public function getSrcIsFiatAttribute()
    {
        return $this->src_type =='\App\Models\Country';
    }
	
    public function getDstIsFiatAttribute()
    {
        return $this->dst_type =='\App\Models\Country';
    }
    
}
