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
     * Create an array of options to fulfil HTTP Cache-Control header.
     *
     * @return array<string, bool|int>
     */
    public function makeHeaderOptions(): array
    {
        $maxAge = ! empty($this->maxAgeList)
            ? min($this->maxAgeList)
            : 0;
        if (0 === $maxAge) {
            $headerOptions['no_cache'] = true;
        } else {
            $headerOptions['max_age'] = $maxAge;
        }

        if (empty($this->scopeList) || in_array('PRIVATE', $this->scopeList)) {
            $headerOptions['private'] = true;
        } else {
            $headerOptions['public'] = true;
        }

        return $headerOptions;
    }
}
