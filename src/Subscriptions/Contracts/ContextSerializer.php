<?php

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

interface ContextSerializer
{
    /**
     * Serialize the context.
     *
     * @return string
     */
    public function serialize(GraphQLContext $context);

    /**
     * Unserialize the context.
     *
     * @return \Nuwave\Lighthouse\Support\Contracts\GraphQLContext
     */
    public function unserialize(string $context);
}
