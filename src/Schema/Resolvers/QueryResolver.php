<?php

namespace Nuwave\Lighthouse\Schema\Resolvers;

use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Schema\Resolvers\NodeResolver;

class QueryResolver extends FieldResolver
{
    /**
     * Generate a GraphQL type from a field.
     *
     * @return array
     */
    public function generate()
    {
        return [
            'args' => $this->getArgs()->toArray(),
            'type' => $this->getType(),
            'resolve' => $this->resolver,
        ];
    }

    /**
     * Get collection of field arguments.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getArgs()
    {
        return collect($this->field->arguments)
            ->mapWithKeys(function (InputValueDefinitionNode $arg) {
                // TODO: Check for argument directives. Use resolver if defined,
                // wrap w/ middleware (i.e., rules)
                return [$arg->name->value => [
                    'type' => NodeResolver::resolve($arg->type),
                ]];
            });
    }

    /**
     * Get type that mutation will resolve to.
     *
     * @return mixed
     */
    protected function getType()
    {
        return NodeResolver::resolve($this->field->type);
    }
}
