<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\Resolvers\NodeResolver;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddleware;

class ArgumentFactory
{
    /**
     * Enrich the argument value and apply directives on it.
     *
     * @param ArgumentValue $argumentValue
     *
     * @return ArgumentValue
     */
    public static function handle(ArgumentValue $argumentValue): ArgumentValue
    {
        $argumentValue->setType(NodeResolver::resolve($argumentValue->getArg()->type));

        return self::applyMiddleware($argumentValue);
    }

    /**
     * Apply argument middleware.
     *
     * @param ArgumentValue $argumentValue
     *
     * @return ArgumentValue
     */
    protected static function applyMiddleware(ArgumentValue $argumentValue): ArgumentValue
    {
        return graphql()->directives()->argMiddleware($argumentValue->getArg())
            ->reduce(function (ArgumentValue $argumentValue, ArgMiddleware $middleware) {
                return $middleware->handleArgument($argumentValue);
            }, $argumentValue);
    }

    /**
     * @param Collection $argumentValues
     *
     * @return array
     */
    public static function convertToExecutable(Collection $argumentValues): array
    {
        return $argumentValues->mapWithKeys(function (ArgumentValue $argumentValue) {
            return [$argumentValue->getArgName() =>
                ['type' => $argumentValue->getType()]
            ];
        })->toArray();
    }
}
