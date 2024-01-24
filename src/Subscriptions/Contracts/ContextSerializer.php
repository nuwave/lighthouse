<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

interface ContextSerializer
{
    /** Serialize the context. */
    public function serialize(GraphQLContext $context): string;

    /** Unserialize the context. */
    public function unserialize(string $context): GraphQLContext;
}
