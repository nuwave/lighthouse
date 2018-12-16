<?php

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

/**
 * The GraphQL context can have different implementations,
 * so we can not have a uniform serializer for it.
 */
interface ContextSerializer
{
    /**
     * Serialize the context.
     *
     * @param mixed $context
     *
     * @return string
     */
    public function serialize($context);

    /**
     * Unserialize the context.
     *
     * @param string $context
     *
     * @return mixed
     */
    public function unserialize($context);
}
