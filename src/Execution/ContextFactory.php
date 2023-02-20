<?php

namespace Nuwave\Lighthouse\Execution;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\Context;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class ContextFactory implements CreatesContext
{
    public function generate(Request $request): GraphQLContext
    {
        return new Context($request);
    }
}
