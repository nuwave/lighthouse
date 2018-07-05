<?php

if (! function_exists('graphql')) {
    /**
     * Get instance of graphql container.
     *
     * @return \Nuwave\Lighthouse\GraphQL
     */
    function graphql(): \Nuwave\Lighthouse\GraphQL
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
    function auth(): \Illuminate\Auth\AuthManager
    {
        return app('auth');
    }
}

if (! function_exists('schema')) {
    /**
     * Get instance of schema container.
     *
     * @return \Nuwave\Lighthouse\Schema\TypeRegistry
     * @deprecated Use graphql()->types() directly in the future
     */
    function schema(): \Nuwave\Lighthouse\Schema\TypeRegistry
    {
        return graphql()->types();
    }
}

if (! function_exists('directives')) {
    /**
     * Get instance of directives container.
     *
     * @return \Nuwave\Lighthouse\Schema\DirectiveRegistry
     * @deprecated Use graphql()->directives() directly in the future
     */
    function directives(): \Nuwave\Lighthouse\Schema\DirectiveRegistry
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
    function config_path(string $path = ''): string
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
    function app_path(string $path = ''): string
    {
        return app()->basePath().'/app'.($path ? '/'.$path : $path);
    }
}

if (! function_exists('resolve')) {
    /**
     * Resolve a service from the container.
     *
     * @param string $name
     *
     * @return mixed
     */
    function resolve(string $name)
    {
        return app($name);
    }
}
