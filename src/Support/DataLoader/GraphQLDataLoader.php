<?php

namespace Nuwave\Lighthouse\Support\DataLoader;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;

abstract class GraphQLDataLoader
{
    /**
     * Name of Data Loader.
     *
     * @var string
     */
    protected $name = '';

    /**
     * Prefetched data.
     *
     * @var array
     */
    protected $data = [];

    /**
     * Available child loaders.
     *
     * @var array
     */
    protected $children = [];

    /**
     * Resolve data.
     *
     * @param  string $key
     * @param  mixed  $root
     * @return mixed
     */
    public function load($key, $root)
    {
        if ($dataLoader = $this->getChildLoader($key)) {
            return $dataLoader->loadDataByKey($this->getName(), $this->getKey($root));
        }

        return null;
    }

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
     * Get key for root object.
     *
     * @param  mixed $root
     * @return mixed
     */
    public function getKey($root)
    {
        if ($root instanceof Model) {
            return $root->getKey();
        }
    }

    /**
     * Resolve child loader.
     *
     * @param  mixed $root
     * @param  array $fields
     * @return mixed
     */
    public function resolveChildren($root, array $fields)
    {
        collect($fields)->each(function ($field, $key) use ($root) {
            if ($dataLoader = $this->getChildLoader($key)) {
                $method = $this->getChildResolveMethod($key, $root);
                $children = method_exists($dataLoader, $method) ?
                    call_user_func_array([$dataLoader, $method], [$root, array_get($field, 'args', []), $field])
                    : null;

                if ($children) {
                    if ($root instanceof Collection) {
                        $children = $children->each(function ($child) use ($dataLoader, $key) {
                            $dataLoader->storeDataByKey(
                                $this->getName(),
                                $this->getKey($child),
                                $child->getAttribute($key)
                            );
                        })
                        ->pluck($key)
                        ->collapse();
                    } else {
                        $dataLoader->storeDataByKey($this->getName(), $this->getKey($root), $children);
                    }

                    $dataLoader->resolveChildren($children, array_get(
                        $field,
                        'children.edges.children.node.children',
                        array_get($field, 'children', $field))
                    );
                }
            }
        });
    }

    /**
     * Get all stored data.
     *
     * @return \Illuminate\Support\Collection
     */
    public function allData()
    {
        return collect($this->data);
    }

    /**
     * Load data by key.
     *
     * @param  string $name
     * @param  mixed $key
     * @return mixed
     */
    public function loadDataByKey($name, $key)
    {
        return array_get($this->data, "{$name}.{$key}");
    }

    /**
     * Store data by id.
     *
     * @param  string $name
     * @param  mixed  $key
     * @param  mixed  $data
     * @return void
     */
    public function storeDataByKey($name, $key, $data)
    {
        $this->data[$name] = isset($this->data[$name])
            ? array_add($this->data[$name], $key, $data)
            : [$key => $data];
    }

    /**
     * Set name of Data Loader.
     *
     * @param string $name
     * @return void
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Get short name of data loader.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
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
}
