<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

use GraphQL\Deferred;

abstract class BatchLoader
{
    /**
     * Map from keys to metainfo for resolving.
     *
     * @var array<mixed, array<mixed>>
     */
    protected $keys = [];

    /**
     * Map from keys to resolved values.
     *
     * @var array<mixed, mixed>
     */
    protected $results = [];

    /**
     * Check if data has been loaded.
     *
     * @var bool
     */
    protected $hasLoaded = false;

    /**
     * Schedule a result to be loaded.
     *
     * @param  array<mixed>  $metaInfo
     */
    public function load(string $key, array $metaInfo = []): Deferred
    {
        $this->keys[$key] = $metaInfo;

        return new Deferred(function () use ($key) {
            if (! $this->hasLoaded) {
                $this->results = $this->resolve();
                $this->hasLoaded = true;
            }

            return $this->results[$key];
        });
    }

    /**
     * Schedule multiple results to be loaded.
     *
     * @param  array<mixed>  $keys
     * @param  array<mixed>  $metaInfo
     * @return array<\GraphQL\Deferred>
     */
    public function loadMany(array $keys, array $metaInfo = []): array
    {
        return array_map(
            function ($key) use ($metaInfo): Deferred {
                return $this->load($key, $metaInfo);
            },
            $keys
        );
    }

    /**
     * Resolve the keys.
     *
     * The result has to be a map from keys to resolved values.
     *
     * @return array<mixed, mixed>
     */
    abstract public function resolve(): array;
}
