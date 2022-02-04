<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Illuminate\Http\Request;

interface CreatesContext
{
    /**
     * Generate GraphQL context.
     *
     * @return \Nuwave\Lighthouse\Support\Contracts\GraphQLContext
     */
    public function generate(Request $request);
}
