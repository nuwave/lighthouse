<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Contracts;

use Illuminate\Http\Request;

interface CreatesContext
{
    /** Generate GraphQL context. */
    public function generate(?Request $request): GraphQLContext;
}
