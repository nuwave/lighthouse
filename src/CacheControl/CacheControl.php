<?php

namespace Nuwave\Lighthouse\CacheControl;

class CacheControl
{
    /**
     * List of maxAges.
     *
     * @var array<int, int>
     */
    protected $maxAgeList = [];

    /**
     * List of scopes.
     *
     * @var array<int, string>
     */
    protected $scopeList = [];

    public function addToMaxAgeList(int $maxAge): void
    {
        $this->maxAgeList[] = $maxAge;
    }

    public function addToScopeList(string $scope): void
    {
        $this->scopeList[] = $scope;
    }

    /**
     * Calculate max-age for HTTP Cache-Control.
     */
    public function calculateMaxAge(): int
    {
        return empty($this->maxAgeList)
            ? 0
            : min($this->maxAgeList);
    }

    /**
     * Calculate scope for HTTP Cache-Control.
     */
    public function calculateScope(): string
    {
        if (empty($this->scopeList) || in_array('PRIVATE', $this->scopeList)) {
            return 'private';
        }

        return 'public';
    }
}
