<?php

namespace Nuwave\Lighthouse\Support\Definition;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use Nuwave\Lighthouse\Support\Traits\GlobalIdTrait;
use Nuwave\Lighthouse\Support\Interfaces\RelayType;

class GraphQLType extends Fluent
{
    use GlobalIdTrait;

    /**
     * Type fields.
     *
     * @return array
     */
    public function fields()
    {
        return [];
    }

    /**
     * Get the identifier of the type.
     *
     * @param  mixed $obj
     * @return mixed
     */
    public function getIdentifier($obj)
    {
        return $obj->id;
    }

    /**
     * Get the attributes of the type.
     *
     * @return array
     */
    public function getAttributes()
    {
        $attributes = array_merge($this->attributes, [
            'fields' => function () {
                return $this instanceof RelayType ? array_merge($this->getRelayIdField(), $this->getFields()) : $this->getFields();
            },
        ]);

        if (sizeof($this->interfaces())) {
            $attributes['interfaces'] = $this->interfaces();
        }

        return $attributes;
    }

    /**
     * Relay global identifier field.
     *
     * @return array
     */
    protected function getRelayIdField()
    {
        return [
            'id' => [
                'type' => Type::nonNull(Type::id()),
                'description' => 'ID of type.',
                'resolve' => function ($obj) {
                    return $this->encodeGlobalId(get_called_class(), $this->getIdentifier($obj));
                },
            ],
        ];
    }

    /**
     * The resolver for a specific field.
     *
     * @param $name
     * @param $field
     * @return \Closure|null
     */
    protected function getFieldResolver($name, $field)
    {
        if (isset($field['resolve'])) {
            return $field['resolve'];
        } elseif (method_exists($this, 'resolve'.studly_case($name).'Field')) {
            $resolver = [$this, 'resolve'.studly_case($name).'Field'];

            return function () use ($resolver) {
                return call_user_func_array($resolver, func_get_args());
            };
        }

        return;
    }

    /**
     * Get the fields of the type.
     *
     * @return array
     */
    public function getFields()
    {
        return Collection::make($this->fields())->transform(function ($field, $name) {
            if (is_string($field)) {
                $field = app($field);
                $field->name = $name;

                return $field->toArray();
            } else {
                $resolver = $this->getFieldResolver($name, $field);

                if ($resolver) {
                    $field['resolve'] = $resolver;
                }

                return $field;
            }
        })->toArray();
    }

    /**
     * Type interfaces.
     *
     * @return array
     */
    public function interfaces()
    {
        return ($this instanceof RelayType && ! $this instanceof GraphQLInterface) ? [app('graphql')->type('node')] : [];
    }

    /**
     * Convert the object to an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->getAttributes();
    }

    /**
     * Convert this class to its ObjectType.
     *
     * @return ObjectType
     */
    public function toType()
    {
        return new ObjectType($this->toArray());
    }

    /**
     * Dynamically retrieve the value of an attribute.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        $attributes = $this->getAttributes();

        return isset($attributes[$key]) ? $attributes[$key] : null;
    }

    /**
     * Dynamically check if an attribute is set.
     *
     * @param  string  $key
     * @return bool
     */
    public function __isset($key)
    {
        return isset($this->getAttributes()[$key]);
    }
}
