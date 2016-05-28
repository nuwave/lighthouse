<?php

namespace Nuwave\Lighthouse\Schema\Generators;

use GraphQL\Type\Definition\Type;

class TypeGenerator
{
    /**
     * Get type definition.
     *
     * @param  mixed $type
     * @return \GraphQL\Type\Definition\ScalarType
     */
    public function fromType($type)
    {
        if (is_object($type)) {
            $type = substr(get_class($type), (strrpos(get_class($type), '\\')) + 1);
        }

        $method = 'get' . studly_case($type);

        if (! method_exists($this, $method)) {
            throw new \Exception("Type generator for [{$type}] does not exist.");
        }

        return call_user_func([$this, $method]);
    }

    /**
     * Get IDType instance.
     *
     * @return \GraphQL\Type\Definition\IDType
     */
    public function getIDType()
    {
        return Type::id();
    }

    /**
     * Get StrinType instance.
     *
     * @return \GraphQL\Type\Definition\StringType
     */
    public function getStringType()
    {
        return Type::string();
    }

    /**
     * Get FloatType instance.
     *
     * @return \GraphQL\Type\Definition\FloatType
     */
    public function getFloatType()
    {
        return Type::float();
    }

    /**
     * Get IntType instance.
     *
     * @return \GraphQL\Type\Definition\IntType
     */
    public function getIntType()
    {
        return Type::int();
    }

    /**
     * Get BooleanType instance.
     *
     * @return \GraphQL\Type\Definition\BooleanType
     */
    public function getBooleanType()
    {
        return Type::boolean();
    }
}
