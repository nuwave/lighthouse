<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class ContextFactory implements CreatesContext
{
    public function generate(?Request $request): GraphQLContext
    {
        return $request
            ? new HttpGraphQLContext($request)
            : Container::getInstance()->make(UserGraphQLContext::class);
    }
}
