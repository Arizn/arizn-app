<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Token;
use Illuminate\Auth\Access\HandlesAuthorization;

class TokenPolicy
{
    use HandlesAuthorization;
	
	public function before($user, $ability)
	{
		if ($user->isAdmin()) {
			return true;
		}
	}
    /**
     * Determine whether the user can view the token.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Token  $token
     * @return mixed
	 
     */
    public function view(User $user, Token $token)
    {
        //
		return true;
    }

    /**
     * Determine whether the user can create tokens.
     *
     * @param  \App\Models\User  $user
     * @return mixed
     */
    public function create(User $user )
    {
        //
		return true;
    }

    /**
     * Determine whether the user can update the token.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Token  $token
     * @return mix;ed
     */
    public function update(User $user, Token $token)
    {
        //
		return $user->id == $token->user_id;
    }

    /**
     * Determine whether the user can delete the token.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Token  $token
     * @return mixed
     */
    public function delete(User $user, Token $token)
    {
        //
		return $user->id == $token->user_id;
    }
}
