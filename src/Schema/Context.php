<?php

namespace Nuwave\Lighthouse\Schema;

use Illuminate\Http\Request;

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
     * @var mixed
     */
    public $user;

    /**
     * Create new context.
     *
     * @param Request $request
     * @param mixed   $user
     */
    public function __construct(Request $request, $user)
    {
        $this->request = $request;
        $this->user = $user;
    }
}
