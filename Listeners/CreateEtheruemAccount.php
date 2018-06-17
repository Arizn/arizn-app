<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Request;
use App\Traits\WalletTrait;

class CreateEtheruemAccount
{
	use WalletTrait;
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(Request $request)
	{
		$this->request = $request;
	}

    /**
     * Handle the event.
     *
     * @param  Login  $event
     * @return void
     */
    public function handle(Login $event )
    {
        //
		if(($event->user->account()->count()==0)){
			$password = $this->request->input('password');
			$this->create_account( $event->user , $password);
		}
    }
}
