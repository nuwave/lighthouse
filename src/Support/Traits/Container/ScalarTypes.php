<?php

namespace Nuwave\Lighthouse\Support\Traits\Container;

use GraphQL\Type\Definition\Type;

trait ScalarTypes
{
    /**
     * ID field.
     *
     * @param  string $description
     * @return array
     */
    public function id($description = '')
    {
        return [
            'type' => Type::id(),
            'description' => $description,
        ];
    }

    /**
     * String field.
     *
     * @param  string $description
     * @return array
     */
    public function string($description = '')
    {
        return [
            'type' => Type::string(),
            'description' => $description,
        ];
    }

    /**
     * Integer field.
     *
     * @param  string $description
     * @return array
     */
    public function int($description = '')
    {
        return [
            'type' => Type::int(),
            'description' => $description,
        ];
    }

    /**
     * Boolean field.
     *
     * @param  string $description
     * @return array
     */
    public function boolean($description = '')
    {
        return [
            'type' => Type::boolean(),
            'description' => $description,
        ];
    }

    /**
     * Float field.
     *
     * @param  string $description
     * @return array
     */
    public function float($description = '')
    {
        return [
            'type' => Type::float(),
            'description' => $description,
        ];
    }
}
