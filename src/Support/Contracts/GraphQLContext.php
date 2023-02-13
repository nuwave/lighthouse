<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface GraphQLContext
{
    /**
     * Get an instance of the authenticated user.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function user();

    /**
     * Get an instance of the current HTTP request.
     *
     * @return \Illuminate\Http\Request
     */
    public function request();

    /**
     * Set the authenticated user.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable|null  $user
     * @return void
     */
    public function setUser(?Authenticatable $user);
}
