<?php

namespace Nuwave\Lighthouse\Schema;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Auth\AuthServiceProvider;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class Context implements GraphQLContext
{
    /**
     * An instance of the incoming HTTP request.
     *
     * @var \Illuminate\Http\Request
     */
    public $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Get instance of authenticated user.
     *
     * May be null since some fields may be accessible without authentication.
     */
    public function user(): ?Authenticatable
    {
        return $this->request->user(AuthServiceProvider::guard());
    }

    public function request(): Request
    {
        return $this->request;
    }
}
