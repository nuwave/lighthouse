<?php

namespace Nuwave\Lighthouse\Support\DataLoader;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;

abstract class GraphQLDataFetcher
{
    /**
     * Name of Data Fetcher.
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
     * Available child data fetchers.
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
        if ($dataFetcher = $this->getChildFetcher($key)) {
            return $dataFetcher->loadDataByKey($this->getName(), $this->getKey($root));
        }
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
     * Resolve child data fetcher.
     *
     * @param  mixed $root
     * @param  array $fields
     * @return mixed
     */
    public function resolveChildren($root, array $fields)
    {
        Collection::make($fields)->each(function ($field, $key) use ($root) {
            if ($dataFetcher = $this->getChildFetcher($key)) {
                $method = $this->getChildResolveMethod($key, $root);
                $children = method_exists($dataFetcher, $method) ?
                    call_user_func_array([$dataFetcher, $method], [$root, array_get($field, 'args', []), $field])
                    : null;

                if ($children) {
                    if ($root instanceof Collection) {
                        $children = $children->each(function ($child) use ($dataFetcher, $key) {
                            $dataFetcher->storeDataByKey(
                                $this->getName(),
                                $this->getKey($child),
                                $child->getAttribute($key)
                            );
                        })
                        ->pluck($key)
                        ->collapse();
                    } else {
                        $dataFetcher->storeDataByKey($this->getName(), $this->getKey($root), $children);
                    }

                    $dataFetcher->resolveChildren($children, array_get(
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
        return new Collection($this->data);
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
     * Set name of Data Fetcher.
     *
     * @param string $name
     * @return void
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Get short name of data fetcher.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Determine if child data fetcher is available.
     *
     * @param  string  $key
     * @return bool
     */
    protected function hasChildFetcher($key)
    {
        return isset($this->children[$key]);
    }

    /**
     * Get child DataFetcher by key.
     *
     * @param  string $key
     * @return self
     */
    protected function getChildFetcher($key)
    {
        if ($this->hasChildFetcher($key)) {
            return app($this->children[$key]);
        }
    }

    /**
     * Get name of method to resolve child data fetcher.
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
     * Generate method name to call on child data fetcher.
     *
     * @param  string  $key
     * @param  bool $plural
     * @return string
     */
    protected function generateMethodName($key, $plural = false)
    {
        $name = $plural ? str_plural($this->getName()) : $this->getName();

        return camel_case($name.'_'.str_plural($key));
    }
}
