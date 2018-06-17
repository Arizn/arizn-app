<?php


namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Request;

class VerifiedScope implements Scope
{
   
    public function apply(Builder $builder, Model $model)
    {
        if (auth()->user()->hasRole('admin')) {
            return $builder;
        }
        return $builder->where('email_verified', 1)->where('phone_verified', 1);
    }
}
