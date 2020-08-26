<?php

namespace Nuwave\Lighthouse\Support\Contracts;

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
}
