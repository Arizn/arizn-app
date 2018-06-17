<?php


namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Request;

class NetScope implements Scope
{
    
    public function apply(Builder $builder, Model $model)
    {
        if (auth()->user() && auth()->user()->hasRole('admin')) {
            return $builder;
        }
		if (env('APP_NAME') == 'ICOFURY') { // demo
            return $builder;
        }
        
        return $builder->where('net', settings('ETHEREUMNETWORK'));
    }
}
