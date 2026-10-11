<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Contracts;

interface SerializesContext
{
    /** Serialize the context. */
    public function serialize(GraphQLContext $context): string;

    /** Unserialize the context. */
    public function unserialize(string $context): GraphQLContext;
}
