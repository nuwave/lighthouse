<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use Nuwave\Lighthouse\Schema\Resolvers\NodeResolver;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddleware;

class ArgumentFactory
{
    /**
     * Convert argument definition to type.
     *
     * @param ArgumentValue $value
     *
     * @return array
     */
    public function handle(ArgumentValue $value)
    {
        $value->setType(NodeResolver::resolve($value->getArg()->type));

        return $this->applyMiddleware($value)->getValue();
    }

    /**
     * Apply argument middleware.
     *
     * @param ArgumentValue $value
     *
     * @return ArgumentValue
     */
    protected function applyMiddleware(ArgumentValue $value)
    {
        return directives()->argMiddleware($value->getArg())
            ->reduce(function (ArgumentValue $value, ArgMiddleware $middleware) {
                return $middleware->handleArgument(
                    $value->setMiddlewareDirective($middleware->name())
                );
            }, $value);
    }
}
