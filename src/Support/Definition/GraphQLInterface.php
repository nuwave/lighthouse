<?php

namespace Nuwave\Lighthouse\Support\Definition;

use GraphQL\Type\Definition\InterfaceType;

class GraphQLInterface extends GraphQLType
{
    /**
     * Get resolver for interface.
     *
     * @return mixed
     */
    protected function getTypeResolver()
    {
        if (! method_exists($this, 'resolveType')) {
            return;
        }

        $resolver = [$this, 'resolveType'];

        return function () use ($resolver) {
            $args = func_get_args();

            return call_user_func_array($resolver, $args);
        };
    }

    /**
     * Get the attributes from the container.
     *
     * @return array
     */
    public function getAttributes()
    {
        $attributes = parent::getAttributes();
        $resolver = $this->getTypeResolver();

        if (isset($resolver)) {
            $attributes['resolveType'] = $resolver;
        }

        return $attributes;
    }

    /**
     * Conver to interface type.
     *
     * @return InterfaceType
     */
    public function toType()
    {
        return new InterfaceType($this->toArray());
    }
}
