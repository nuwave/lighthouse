<?php

namespace Nuwave\Lighthouse\Schema;

use Illuminate\Http\Request;
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

    /**
     * Create new context.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
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
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
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
