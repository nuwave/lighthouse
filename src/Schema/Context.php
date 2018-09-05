<?php

namespace Nuwave\Lighthouse\Schema;

use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\User;

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
     * @var User
     */
    public $user;

    /**
     * Create new context.
     *
     * @param Request $request
     * @param User $user
     */
    public function __construct(Request $request, User $user)
    {
        $this->request = $request;
        $this->user = $user;
    }
}
