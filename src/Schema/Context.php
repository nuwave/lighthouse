<?php

namespace Nuwave\Lighthouse\Schema;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Auth\AuthServiceProvider;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * @property \Illuminate\Contracts\Auth\Authenticatable|null $user
 */
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

    /**
     * Lazily loads $user
     * this helps use the correct guard if changed via @guard
     */
    public function __get($name)
    {
        if ($name === 'user') {
            return $this->user();
        }

        return $this->{$name};
    }
}
