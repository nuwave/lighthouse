<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Illuminate\Foundation\Auth\User as Authenticatable;

interface GraphQLContext
{
    /**
     * Get instance of authorized user.
     *
     * @return Authenticatable|null
     */
    public function user();

    /**
     * Get instance of request.
     *
     * @return \Illuminate\Http\Request
     */
    public function request();
}
