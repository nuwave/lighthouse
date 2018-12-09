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

if (! function_exists('namespace_classname')) {
    /**
     * Attempt to find a given class in the given namespaces.
     *
     * If the class itself exists, it is simply returned as is.
     * Else, the given namespaces are tried in order.
     *
     * @param string $classCandidate
     * @param array $namespacesToTry
     *
     * @return string|false
     */
    function namespace_classname(string $classCandidate, array $namespacesToTry = [])
    {
        if(\class_exists($classCandidate)){
            return $classCandidate;
        }

        // Stop if the class is found or we are out of namespaces to try
        while(!empty($namespacesToTry)){
            // Pop off the first namespace and try it
            $className = \array_shift($namespacesToTry) . '\\' . $classCandidate;

            if(\class_exists($className)){
                return $className;
            }
        }

        return false;
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

        // TODO convert this back once we require PHP 7.1
        // return \Closure::fromCallable([resolve($className), $methodName]);
        return function () use ($className, $methodName) {
            return app($className)->{$methodName}(...func_get_args());
        };
    }
}
