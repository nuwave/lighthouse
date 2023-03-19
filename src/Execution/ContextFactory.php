<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\HttpGraphQLContext;
use Nuwave\Lighthouse\Schema\UserGraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;

class ContextFactory implements CreatesContext
{
    public function generate(?Request $request): HttpGraphQLContext
    {
        return $request
            ? new HttpGraphQLContext($request)
            : Container::getInstance()->make(UserGraphQLContext::class);
    }
}
