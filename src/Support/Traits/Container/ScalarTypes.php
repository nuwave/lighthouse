<?php

namespace Nuwave\Lighthouse\Support\Traits\Container;

use GraphQL\Type\Definition\Type;

trait ScalarTypes
{
    /**
     * ID field.
     *
     * @param  array|string $config
     * @return array
     */
    public function id($config = [])
    {
        $description = is_string($config) ? $config : '';
        $config = is_array($config) ? $config : [];

        return array_merge([
            'type' => Type::id(),
            'description' => $description,
        ], $config);
    }

    /**
     * String field.
     *
     * @param  array|string $config
     * @return array
     */
    public function string($config = [])
    {
        $description = is_string($config) ? $config : '';
        $config = is_array($config) ? $config : [];

        return array_merge([
            'type' => Type::string(),
            'description' => $description,
        ], $config);
    }

    /**
     * Integer field.
     *
     * @param  array|string $config
     * @return array
     */
    public function int($config = [])
    {
        $description = is_string($config) ? $config : '';
        $config = is_array($config) ? $config : [];

        return array_merge([
            'type' => Type::int(),
            'description' => $description,
        ], $config);
    }

    /**
     * Boolean field.
     *
     * @param  array|string $config
     * @return array
     */
    public function boolean($config = [])
    {
        $description = is_string($config) ? $config : '';
        $config = is_array($config) ? $config : [];

        return array_merge([
            'type' => Type::boolean(),
            'description' => $description,
        ], $config);
    }

    /**
     * Float field.
     *
     * @param  array|string $config
     * @return array
     */
    public function float($config = [])
    {
        $description = is_string($config) ? $config : '';
        $config = is_array($config) ? $config : [];

        return array_merge([
            'type' => Type::float(),
            'description' => $description,
        ], $config);
    }

    /**
     * Non null field.
     *
     * @param  array $wrappedType
     * @return array
     */
    public function nonNull($wrappedType)
    {
        return array_merge($wrappedType, [
            'type' => Type::nonNull($wrappedType['type'])
        ]);
    }

    /**
     * List type field.
     *
     * @param  array $wrappedType
     * @return array
     */
    public function listOf($wrappedType)
    {
        return array_merge($wrappedType, [
            'type' => Type::listOf($wrappedType['type'])
        ]);
    }
}
