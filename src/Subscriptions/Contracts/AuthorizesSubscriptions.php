<?php

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Illuminate\Http\Request;

interface AuthorizesSubscriptions
{
    /**
     * Is the subscription request authorized?
     *
     * @return bool
     */
    public function authorize(Request $request);
}
