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

    /**
     * An instance of the currently authenticated user.
     *
     * @var \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public $user;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->user = $request->user(AuthServiceProvider::guard());
    }

    /**
     * Get instance of authenticated user.
     *
     * May be null since some fields may be accessible without authentication.
     */
    public function user(): ?Authenticatable
    {
        return $this->user;
    }

    public function request(): Request
    {
        return $this->request;
    }
}
