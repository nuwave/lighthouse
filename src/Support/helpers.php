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

if (! function_exists('auth')) {
    /**
     * Get instance of auth container.
     *
     * @return \Illuminate\Auth\AuthManager
     */
    function auth()
    {
        return app('auth');
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

if (!function_exists('resolve')) {
    /**
     * Resolve a service from the container.
     *
     * @param  string  $name
     * @return mixed
     */
    function resolve($name)
    {
        return app($name);
    }
}
