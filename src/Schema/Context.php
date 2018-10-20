<?php

namespace Nuwave\Lighthouse\Schema;

use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\Authenticatable;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class Context implements GraphQLContext
{
    /**
     * An instance of the incoming HTTP request.
     *
     * @var Request
     */
    public $request;

    /**
     * An instance of the currently authenticated user.
     *
     * @var Authenticatable|null
     */
    public $user;

    /**
     * Create new context.
     *
     * @param Request   $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->user = $request->user();
    }

    /**
     * Get instance of authenticated user.
     *
     * May be null since some fields may be accessible without authentication.
     *
     * @return Authenticatable|null
     */
    public function user()
    {
        return $this->user;
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
