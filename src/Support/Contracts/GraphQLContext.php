<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

interface GraphQLContext
{
    /**
     * Get an instance of the authenticated user.
     */
    public function user(): ?Authenticatable;

    /**
     * Get an instance of the current HTTP request.
     */
    public function request(): Request;

    /**
     * Set the authenticated user.
     */
    public function setUser(?Authenticatable $user): void;
}
