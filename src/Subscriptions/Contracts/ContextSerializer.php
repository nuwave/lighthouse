<?php

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

interface ContextSerializer
{
    /**
     * Serialize the context.
     *
     * @param GraphQLContext $context
     *
     * @return string
     */
    public function serialize(GraphQLContext $context);

    /**
     * Unserialize the context.
     *
     * @param string $context
     *
     * @return GraphQLContext
     */
    public function unserialize(string $context);
}
