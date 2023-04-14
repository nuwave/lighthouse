<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Illuminate\Http\Request;

interface AuthorizesSubscriptions
{
    /** Is the subscription request authorized? */
    public function authorize(Request $request): bool;
}
