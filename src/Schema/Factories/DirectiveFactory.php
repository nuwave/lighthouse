<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\FieldArgument;
use Nuwave\Lighthouse\Schema\Resolvers\NodeResolver;

class DirectiveFactory
{
    /**
     * Transform a directive definition into a directive.
     *
     * @param DirectiveDefinitionNode $directive
     *
     * @return Directive
     */
    public function toDirective(DirectiveDefinitionNode $directive)
    {
        return new Directive([
            'name' => $directive->name->value,
            'locations' => collect($directive->locations)->map(function ($location) {
                return $location->value;
            })->toArray(),
            'args' => collect($directive->arguments)->map(function (InputValueDefinitionNode $argument) {
                return new FieldArgument([
                    'name' => $argument->name->value,
                    'defaultValue' => $argument->defaultValue->value,
                    'description' => $argument->description,
                    'type' => NodeResolver::resolve($argument->type),
                ]);
            })->toArray(),
            'astNode' => $directive,
        ]);
    }
}
