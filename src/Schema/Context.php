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
     * Create new context.
     *
     * @param \Illuminate\Http\Request $request
     */
    public function __construct($request)
    {
        $this->request = $request;
    }
}
