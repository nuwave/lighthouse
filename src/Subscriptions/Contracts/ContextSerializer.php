<?php

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

interface ContextSerializer
{
    /**
     * Serialize the context.
     *
     * @param  \Nuwave\Lighthouse\Support\Contracts\GraphQLContext  $context
     * @return string
     */
    public function serialize(GraphQLContext $context);

    /**
     * Unserialize the context.
     *
     * @param  string  $context
     * @return \Nuwave\Lighthouse\Support\Contracts\GraphQLContext
     */
    public function unserialize(string $context);
}
