<?php

namespace Nuwave\Lighthouse\Support\DataLoader;

use GraphQL\Deferred;
use Illuminate\Support\Collection;

abstract class BatchLoader
{
    /**
     * Keys to resolve.
     *
     * @var array
     */
    protected $keys = [];

    /**
     * Map of loaded results.
     *
     * [key => resolvedValue]
     *
     * @var array
     */
    private $results = [];

    /**
     * Check if data has been loaded.
     *
     * @var bool
     */
    private $hasLoaded = false;

    /**
     * Generate a unique key for the instance, using the path in the query.
     *
     * @param array $path
     *
     * @return string
     */
    public static function instanceKey(array $path): string
    {
        return collect($path)
            ->filter(function ($path) {
                // Ignore numeric path entries, as those signify an array of fields
                // Those are the very purpose for this batch loader, so they must not be included.
                return !is_numeric($path);
            })
            ->implode('_');
    }

    /**
     * Load object by key.
     *
     * @param mixed $key
     * @param array $metaInfo
     *
     * @return Deferred
     */
    public function load($key, array $metaInfo = []): Deferred
    {
        $this->keys[$key] = $metaInfo;

        return new Deferred(function () use ($key) {
            if (!$this->hasLoaded) {
                $this->results = $this->resolve();
                $this->hasLoaded = true;
            }

            return $this->results[$key];
        });
    }

    /**
     * @return Collection
     */
    protected function getParentModels(): Collection
    {
        return collect($this->keys)->pluck('parent');
    }

    /**
     * Resolve the keys.
     *
     * The result has to be a map: [key => result]
     */
    abstract public function resolve(): array;
}
