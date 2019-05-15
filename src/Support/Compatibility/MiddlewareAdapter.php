<?php

namespace Nuwave\Lighthouse\Support\Compatibility;

interface MiddlewareAdapter
{
    /**
     * Get all of the defined middleware short-hand names.
     *
     * @return string[]
     */
    public function getMiddleware(): array;

    /**
     * Get all of the defined middleware groups.
     *
     * @return string[]
     */
    public function getMiddlewareGroups(): array;
}
