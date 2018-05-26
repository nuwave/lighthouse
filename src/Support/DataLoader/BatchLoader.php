<?php

namespace Nuwave\Lighthouse\Support\DataLoader;

use GraphQL\Deferred;

abstract class BatchLoader
{
    /**
     * Keys to resolve.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $keys = [];

    /**
     * Check if data has been loaded.
     *
     * @var bool
     */
    protected $hasLoaded = false;

    /**
     * Generate key for field.
     *
     * @param \Illuminate\Database\Eloquent\Model  $root
     * @param \GraphQL\Type\Definition\ResolveInfo $info
     * @param string                               $relation
     *
     * @return string
     */
    public static function key($root, $relation, $info = null)
    {
        $path = ! empty(data_get($info, 'path')) ? array_last($info->path) : $relation;

        return camel_case($root->getTable().'_'.$path);
    }

    /**
     * Load object by key.
     *
     * @param mixed $key
     * @param array $data
     *
     * @return Deferred
     */
    public function load($key, array $data = [])
    {
        $this->keys[$key] = $data;

        return new Deferred(function () use ($key) {
            if (! $this->hasLoaded) {
                $this->resolve();
                $this->hasLoaded = true;
            }

            return array_get($this->keys, "$key.value");
        });
    }

    /**
     * Set key value.
     *
     * @param mixed $key
     * @param mixed $value
     */
    protected function set($key, $value)
    {
        if ($field = array_get($this->keys, $key)) {
            $this->keys[$key] = array_merge($field, compact('value'));
        }
    }

    /**
     * Resolve keys.
     */
    abstract public function resolve();
}
