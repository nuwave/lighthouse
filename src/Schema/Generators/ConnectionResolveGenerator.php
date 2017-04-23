<?php

namespace Nuwave\Lighthouse\Schema\Generators;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Support\Traits\GlobalIdTrait;

class ConnectionResolveGenerator
{
    use GlobalIdTrait;

    /**
     * Attempt to auto-resolve connection.
     *
     * @param  mixed $root
     * @param  array $args
     * @param  mixed $context
     * @param  ResolveInfo $info
     * @param  string $name
     * @return LengthAwarePaginator
     */
    public function resolve($root, array $args, $context, ResolveInfo $info, $name)
    {
        $items = $this->getItems($root, $info, $name);

        if (is_array($items)) {
            $items = new Collection($items);
        } elseif (! $items instanceof Collection) {
            return $items;
        }

        return $items->toConnection($args);
    }

    /**
     * @param             $collection
     * @param ResolveInfo $info
     * @param             $name
     * @return Collection|array
     */
    protected function getItems($collection, ResolveInfo $info, $name)
    {
        if ($collection instanceof Model) {
            if (in_array($name, array_keys($collection->getRelations()))) {
                return $collection->{$name};
            }

            return method_exists($collection, $name)
                ? $collection->{$name}()->select($this->getSelectFields($info))->get()
                : $collection->getAttribute($name);
        } elseif (is_object($collection) && method_exists($collection, 'get')) {
            return $collection->get($name);
        } elseif (is_array($collection) && isset($collection[$name])) {
            return new Collection($collection[$name]);
        }

        return [];
    }

    /**
     * Select only certain fields on queries instead of all fields.
     *
     * @param ResolveInfo $info
     * @return array
     */
    protected function getSelectFields(ResolveInfo $info)
    {
        $camel = config('relay.camel_case');
        $fields = array_get($info->getFieldSelection(2), 'edges.node');

        return Collection::make($fields)->reject(function ($value) {
            is_array($value);
        })->keys()->transform(function ($value) use ($camel) {
            return $camel ? snake_case($value) : $value;
        })->toArray();
    }
}
