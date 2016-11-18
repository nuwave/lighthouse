<?php

if (! function_exists('graphql')) {
    /**
     * Get instance of graphql container.
     *
     * @return \Nuwave\Lighthouse\GraphQL
     */
    function graphql()
    {
        return app('graphql');
    }
}

if (! function_exists('schema')) {
    /**
     * Get instance of schema builder.
     *
     * @return \Nuwave\Lighthouse\Schema\SchemaBuilder
     */
    function schema()
    {
        return GraphQL::schema();
    }
}

if (! function_exists('dataLoader')) {
    /**
     * Get instance of dataLoader.
     *
     * @param  string $loader
     * @return \Nuwave\Lighthouse\Support\DataLoader\GraphQLDataLoader
     */
    function dataLoader($loader)
    {
        return GraphQL::dataLoader($loader);
    }
}
