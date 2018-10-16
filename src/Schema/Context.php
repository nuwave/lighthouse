<?php

namespace Nuwave\Lighthouse\Schema;

use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\Authenticatable as User;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class Context implements GraphQLContext
{
    /**
     * Http request.
     *
     * @var Request
     */
    public $request;

    /**
     * Create new context.
     *
     * @param Request   $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Get instance of authenticated user.
     *
     * May be null since some fields may be accessible without authentication.
     *
     * @return User|null
     */
    public function user()
    {
        return $this->request->user();
    }

    /**
     * Get instance of request.
     *
     * @return \Illuminate\Http\Request
     */
    public function request(): Request
    {
        return $this->request;
    }
}
