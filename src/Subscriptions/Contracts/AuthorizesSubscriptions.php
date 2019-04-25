<?php

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Illuminate\Http\Request;

interface AuthorizesSubscriptions
{
    /**
     * Authorize subscription request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public function authorize(Request $request);
}
