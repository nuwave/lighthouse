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
     * Subscription Event
     * 
     * @var mixed
     */
    public $event;

    /**
     * Create new context.
     *
     * @param \Illuminate\Http\Request $request
     * @param mixed                    $user
     * @param mixed                    $event
     */
    public function __construct($request, $user, $event = null)
    {
        $this->request = $request;
        $this->user = $user;
        $this->event = $event;
    }
}
