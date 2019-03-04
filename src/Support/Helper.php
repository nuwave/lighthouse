<?php

namespace Nuwave\Lighthouse\Support;

use Closure;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Exceptions\DefinitionException;

class Helper
{
    /**
     * Get instance of graphql container.
     *
     * @return \Nuwave\Lighthouse\GraphQL
     */
    public static function graphql(): GraphQL
    {
        return app(GraphQL::class);
    }

    /**
     * Construct a closure that passes through the arguments.
     *
     * @param  string  $className This class is resolved through the container.
     * @param  string  $methodName The method that gets passed the arguments of the closure.
     * @return \Closure
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    public static function constructResolver(string $className, string $methodName): Closure
    {
        if (! method_exists($className, $methodName)) {
            throw new DefinitionException("Method '{$methodName}' does not exist on class '{$className}'");
        }

        return Closure::fromCallable([app($className), $methodName]);
    }
}
