<?php

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

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
