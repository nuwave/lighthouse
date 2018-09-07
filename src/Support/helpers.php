<?php

use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Schema\DirectiveRegistry;

if (! function_exists('graphql')) {
    /**
     * Get instance of graphql container.
     *
     * @return \Nuwave\Lighthouse\GraphQL
     */
    function graphql()
    {
        return resolve('graphql');
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
        return resolve('auth');
    }
}

if (! function_exists('schema')) {
    /**
     * Get instance of schema container.
     *
     * @return \Nuwave\Lighthouse\Schema\TypeRegistry
     * @deprecated Use resolve(TypeRegistry::class) directly in the future
     */
    function schema()
    {
        return resolve(TypeRegistry::class);
    }
}

if (! function_exists('directives')) {
    /**
     * Get instance of directives container.
     *
     * @return \Nuwave\Lighthouse\Schema\DirectiveRegistry
     * @deprecated Use resolve(DirectiveRegistry::class) directly in the future
     */
    function directives()
    {
        return resolve(DirectiveRegistry::class);
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

if (! function_exists('resolve')) {
    /**
     * Resolve a service from the container.
     *
     * @param string $name
     *
     * @return mixed
     */
    function resolve($name)
    {
        return app($name);
    }
}
