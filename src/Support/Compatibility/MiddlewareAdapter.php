<?php

namespace Nuwave\Lighthouse\Support\Compatibility;

interface MiddlewareAdapter
{
    /**
     * Get all of the defined middleware short-hand names.
     *
     * @return array<string>
     */
    public function getMiddleware(): array;

    /**
     * Get all of the defined middleware groups.
     *
     * @return array<string>
     */
    public function getMiddlewareGroups(): array;
}
