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
     * Create new context.
     *
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }
}
