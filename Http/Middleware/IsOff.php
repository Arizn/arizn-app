<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Storage;

class IsOff
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null){
    // retrieve setting from database and turn into key value array
	if( !$request->is('install')&& !$request->is('install/*')){
		if(!$this->alreadyInstalled()){
			return redirect('/install');
		}
		if( setting('maintenance') == 'yes' && !$request->is('admin') && !$request->is('admin/*')&&!$request->is('login')){
			return response()->view('errors.maintenance', [], 500);
		}
	}
	
		return $next($request);
	}
	
	public function alreadyInstalled()
    {
        return file_exists(storage_path('installed'));
    }

}