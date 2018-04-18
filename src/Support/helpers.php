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
     * Get instance of schema container.
     *
     * @return \Nuwave\Lighthouse\Schema\SchemaBuilder
     */
    function schema()
    {
        return graphql()->schema();
    }
}

if (! function_exists('resolve')) {
    /**
    * Helper method for Lumen
    *
    * Resolves a class instance out of the container
    */
    function resolve($args)
    {
        return app()->make($args);
    }
}

if (! function_exists('directives')) {
    /**
     * Get instance of directives container.
     *
     * @return \Nuwave\Lighthouse\Schema\Factories\DirectiveFactory
     */
    function directives()
    {
        return graphql()->directives();
    }
}

if (! function_exists('config_path')) {
    /**
     * Get base configuration path.
     *
     * @param string $path
     *
     * @return string
     */
    function config_path($path = '')
    {
        return app()->basePath().'/config'.($path ? '/'.$path : $path);
    }
}

if (! function_exists('app_path')) {
    /**
     * Get base app path.
     *
     * @param string $path
     *
     * @return string
     */
    function app_path($path = '')
    {
        return app()->basePath().'/app'.($path ? '/'.$path : $path);
    }
}
