<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;

interface GraphQLContext
{
    /**
     * Get an instance of the authenticated user.
     *
     * @return User|null
     */
    public function user();

    /**
     * Get an instance of the current HTTP request.
     *
     * @return Request
     */
    public function request();
}
