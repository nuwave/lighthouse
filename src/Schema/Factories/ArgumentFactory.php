<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use Nuwave\Lighthouse\Support\Pipeline;
use Nuwave\Lighthouse\Schema\DirectiveRegistry;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddleware;
use Nuwave\Lighthouse\Schema\Conversion\DefinitionNodeConverter;

class ArgumentFactory
{
    /** @var DirectiveRegistry */
    protected $directiveRegistry;
    /** @var Pipeline */
    protected $pipeline;
    /** @var DefinitionNodeConverter */
    protected $definitionNodeConverter;

    /**
     * ArgumentFactory constructor.
     * @param DirectiveRegistry $directiveRegistry
     * @param Pipeline $pipeline
     * @param DefinitionNodeConverter $definitionNodeConverter
     */
    public function __construct(DirectiveRegistry $directiveRegistry, Pipeline $pipeline, DefinitionNodeConverter $definitionNodeConverter)
    {
        $this->directiveRegistry = $directiveRegistry;
        $this->pipeline = $pipeline;
        $this->definitionNodeConverter = $definitionNodeConverter;
    }

    /**
     * Convert argument definition to type.
     *
     * @param ArgumentValue $value
     *
     * @return array
     */
    public function handle(ArgumentValue $value): array
    {
        $value->setType(
            $this->definitionNodeConverter->toType(
                $value->getArg()->type
            )
        );

        return $this->applyMiddleware($value)->getValue();
    }

    /**
     * Apply argument middleware.
     *
     * @param ArgumentValue $value
     *
     * @return ArgumentValue
     */
    protected function applyMiddleware(ArgumentValue $value): ArgumentValue
    {
        return $this->pipeline
            ->send($value)
            ->through($this->directiveRegistry->argMiddleware($value->getArg()))
            ->via('handleArgument')
            ->always(function (ArgumentValue $value, ArgMiddleware $middleware) {
                return $value->setMiddlewareDirective($middleware->name());
            })
            ->then(function (ArgumentValue $value) {
                return $value;
            });
    }
}
