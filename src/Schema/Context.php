<?php

namespace Nuwave\Lighthouse\Schema;

use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\Authenticatable as User;

class Context
{
    /**
     * Http request.
     *
     * @var Request
     */
    public $request;

    /**
     * Authenticated user.
     *
     * May be null since some fields may be accessible without authentication.
     *
     * @var User|null
     */
    public $user;

    /**
     * Create new context.
     *
     * @param Request $request
     * @param User|null $user
     */
    public function __construct(Request $request, User $user = null)
    {
        $this->request = $request;
        $this->user = $user;
    }
}
