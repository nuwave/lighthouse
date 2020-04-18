<?php

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Illuminate\Http\Request;

interface AuthorizesSubscriptions
{
    /**
     * Authorize subscription request.
     *
     * @return bool
     */
    public function authorize(Request $request);
}
