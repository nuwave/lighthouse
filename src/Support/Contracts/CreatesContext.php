<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Illuminate\Http\Request;

interface CreatesContext
{
    /**
     * Generate GraphQL context.
     *
     * @param  \Illuminate\Http\Request $request
     *
     * @return GraphQLContext
     */
    public function generate(Request $request);
}
