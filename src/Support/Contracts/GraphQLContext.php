<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\User;

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
