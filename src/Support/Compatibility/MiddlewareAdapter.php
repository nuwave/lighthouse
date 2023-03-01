<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Compatibility;

interface MiddlewareAdapter
{
    /**
     * Get all the defined middleware shorthand names.
     *
     * @return array<string>
     */
    public function getMiddleware(): array;

    /**
     * Get all the defined middleware groups.
     *
     * @return array<string>
     */
    public function getMiddlewareGroups(): array;
}
