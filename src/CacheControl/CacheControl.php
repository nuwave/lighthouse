<?php

namespace Nuwave\Lighthouse\CacheControl;

final class CacheControl
{
    /**
     * List of maxAges.
     *
     * @var array<int>
     */
    private $maxAgeList = [];

    /**
     * List of scopes.
     *
     * @var array<string>
     */
    private $scopeList = [];

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
        // Early return if @cacheControl not used.
        if (empty($this->scopeList) && empty($this->maxAgeList)) {
            return $this->appDefaultHeader();
        }

        $maxAge = $this->calculateMaxAge();
        // Set max-age value.
        if (0 === $maxAge) {
            $headerOptions['no_cache'] = true;
        } else {
            $headerOptions['max_age'] = $maxAge;
        }

        // Set scope value.
        if ('public' === $this->calculateScope()) {
            $headerOptions['public'] = true;
        } else {
            $headerOptions['private'] = true;
        }

        return $headerOptions;
    }

    /**
     * Return the minimum maxAge from the list.
     */
    private function calculateMaxAge(): int
    {
        assert(! empty($this->maxAgeList));

        return min($this->maxAgeList);
    }

    /**
     * Return scope as public unless there is a private scope.
     */
    private function calculateScope(): string
    {
        if (in_array('PRIVATE', $this->scopeList)) {
            return 'private';
        }

        return 'public';
    }

    /**
     * App default behavior is no-cache, private.
     *
     * @return array<string, bool>
     */
    private function appDefaultHeader(): array
    {
        $headerOptions['no_cache'] = true;
        $headerOptions['private'] = true;

        return $headerOptions;
    }
}
