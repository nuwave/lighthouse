<?php

namespace Nuwave\Lighthouse\Support\DataLoader;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Collection;

abstract class GraphQLDataLoader
{
    /**
     * Available child loaders.
     *
     * @var array
     */
    protected $children = [];

    /**
     * Prefetch data to load in resolvers.
     *
     * @param  mixed $root
     * @param  ResolveInfo|array $info
     * @return mixed
     */
    public function resolve($root, $info)
    {
        $fields = $info instanceof ResolveInfo ?
            app('graphql')->fieldParser()->fetch($info)
            : $info;

        $this->resolveChildren($root, $fields);

        return $root;
    }

    /**
     * Resolve child loader.
     *
     * @param  mixed $root
     * @param  array $fields
     * @return mixed
     */
    function resolveChildren($root, array $fields)
    {
        collect($fields)->each(function ($field, $key) use ($root) {
            if ($dataLoader = $this->getChildLoader($key)) {
                $method = $this->getChildResolveMethod($key, $root);
                $child = method_exists($dataLoader, $method) ?
                    call_user_func_array([$dataLoader, $method], [$root, $field])
                    : null;

                if ($child) {
                    $this->resolveChildren($child, $field);
                }
            }
        });
    }

    /**
     * Determine if child data loader is available.
     *
     * @param  string  $key
     * @return boolean
     */
    protected function hasChildLoader($key)
    {
        return isset($this->children[$key]);
    }

    /**
     * Get child DataLoader by key.
     *
     * @param  string $key
     * @return self
     */
    protected function getChildLoader($key)
    {
        if (!$this->hasChildLoader($key)) {
            return null;
        }

        return app($this->children[$key]);
    }

    /**
     * Get name of method to resolve child data loader.
     *
     * @param  string $key
     * @param  mixed $root
     * @return string
     */
    protected function getChildResolveMethod($key, $root)
    {
        if ($root instanceof Collection || is_array($root)) {
            return array_get($this->children, $key.'.many', $this->generateMethodName($key, true));
        }

        return array_get($this->children, $key.'.single', $this->generateMethodName($key));
    }

    /**
     * Generate method name to call on child data loader.
     *
     * @param  string  $key
     * @param  boolean $plural
     * @return string
     */
    protected function generateMethodName($key, $plural = false)
    {
        $name = $plural ? str_plural($this->getName()) : $this->getName();

        return camel_case($name.'_'.str_plural($key));
    }

    /**
     * Get short name of data loader.
     *
     * @return string
     */
    abstract public function getName();
}
