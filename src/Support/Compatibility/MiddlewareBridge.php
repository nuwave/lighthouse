<?php

namespace Nuwave\Lighthouse\Support\Compatibility;

interface MiddlewareBridge
{
    /**
     * Get all of the defined middleware short-hand names.
     *
     * @return array
     */
    public function getMiddleware(): array;

    /**
     * Get all of the defined middleware groups.
     *
     * @return array
     */
    public function getMiddlewareGroups(): array;
}
