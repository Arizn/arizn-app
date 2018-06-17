<?php

namespace App\Models;
use Laravel\Passport\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use jeremykenedy\LaravelRoles\Traits\HasRoleAndPermission;
//use App\Traits\WalletTrait;

class User extends Authenticatable
{

    use HasRoleAndPermission, Notifiable,  SoftDeletes, HasApiTokens;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'users';

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
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'activated',
        'token',
        'signup_ip_address',
        'signup_confirmation_ip_address',
        'signup_sm_ip_address',
        'admin_ip_address',
        'updated_ip_address',
        'deleted_ip_address',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    
	
	 protected $visisble = [
        'name',
        'first_name',
        'last_name',
        'email',
    ];

    protected $dates = [
        'deleted_at',
    ];

    /**
     * Build Social Relationships.
     *
     * @var array
     */
    public function social()
    {
        return $this->hasMany('App\Models\Social');
    }
	
	public function transactions()
    {
        return $this->hasMany('App\Models\Transaction');
    }
	public function wallets()
    {
        return $this->hasMany('App\Models\Wallet');
    }
	public function coins()
    {
        return $this->hasMany('App\Models\Token');
    }
	
	public function account()
    {
        return $this->hasOne('App\Models\Account');
    }
	
	public function alerts(){
		return $this->hasMany('App\Models\Alert');
	}

    /**
     * User Profile Relationships.
     *
     * @var array
     */
    public function profile()
    {
        return $this->hasOne('App\Models\Profile');
    }
	
	

    // User Profile Setup - SHould move these to a trait or interface...

    public function profiles()
    {
        return $this->belongsToMany('App\Models\Profile')->withTimestamps();
    }

    public function hasProfile($name)
    {
        foreach ($this->profiles as $profile) {
            if ($profile->name == $name) {
                return true;
            }
        }

        return false;
    }

    public function assignProfile($profile)
    {
        return $this->profiles()->attach($profile);
    }

    public function removeProfile($profile)
    {
        return $this->profiles()->detach($profile);
    }
	
	public function getBalanceAttribute(){
		$symbol = setting('siteCurrency','USD');
		$siteToken = \App\Models\Token::symbol('ETH')->first();
		$siteCurrency = \App\Models\Country::site()->first();
		return $siteToken->symbol.'<span class="eth_balance">'.$this->account->balance.'</span> '.$symbol.number_format($this->account->balance*$siteToken->price, 2 );
		$wallet = $this->wallets()->ofToken($siteToken->id)->first();
		$balance = empty($wallet)?0:$wallet->balance;
		//return $siteToken->symbol.'<span class="'.strtolower($siteToken->symbol).'token_balance">'.$balance.'</span> '.$symbol.number_format($balance*$siteToken->price, 2 );
	}
	
}
