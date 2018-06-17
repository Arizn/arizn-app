<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Alert extends Model
{
	
    use SoftDeletes;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'alerts';

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
		'sender_id',
		'user_id',
		'msg',
    ];
   
    

    protected $dates = [
        'deleted_at',
    ];

    /**
     * Build account Relationships.
     *
     * @var array
     */
   
    public function to()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
	public function from()
    {
        return $this->belongsTo(\App\Models\User::class,'sender_id');
    }
   
}
