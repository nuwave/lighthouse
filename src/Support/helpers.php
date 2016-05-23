<?php

if (! function_exists('graphql')) {
    /**
     * Get instance of graphql container.
     *
     * @return \Nuwave\Relay\GraphQL
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
     * @return \Nuwave\Relay\Schema\SchemaBuilder
     */
    function schema()
    {
        return GraphQL::schema();
    }
}
