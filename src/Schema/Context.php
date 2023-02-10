<?php

namespace Nuwave\Lighthouse\Schema;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Auth\AuthServiceProvider;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * @property-read ?Authenticatable $user
 * @property-read Request $request
 */
class Context implements GraphQLContext
{
    /**
     * An instance of the incoming HTTP request.
     */
    protected Request $request;

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

    public function __get(string $name): mixed
    {
        return method_exists($this, $name)
            ? $this->{$name}()
            : $this->{$name};
    }

    public function __isset(string $name): bool
    {
        return method_exists($this, $name) || property_exists($this, $name);
    }
}
