<?php

namespace Nuwave\Lighthouse\Schema;

class Context
{
    /**
     * Http request.
     *
     * @var \Illuminate\Http\Request
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
     * @param \Illuminate\Http\Request $request
     * @param mixed                    $user
     */
    public function __construct($request, $user)
    {
        $this->request = $request;
        $this->user = $user;
    }
}
