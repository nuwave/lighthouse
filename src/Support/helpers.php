<?php

use Nuwave\Lighthouse\Exceptions\DefinitionException;

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

if (! function_exists('construct_resolver')) {
    /**
     * Construct a closure that passes through the arguments.
     *
     * @param string $className This class is resolved through the container.
     * @param string $methodName The method that gets passed the arguments of the closure.
     *
     * @throws DefinitionException
     *
     * @return \Closure
     */
    function construct_resolver(string $className, string $methodName): \Closure
    {
        if (!method_exists($className, $methodName)) {
            throw new DefinitionException("Method '{$methodName}' does not exist on class '{$className}'");
        }

        return \Closure::fromCallable([resolve($className), $methodName]);
    }
}
