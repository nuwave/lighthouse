<?php

namespace Nuwave\Lighthouse\Schema\Resolvers;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Schema\Resolvers\NodeResolver;
use Nuwave\Lighthouse\Schema\Types\GraphQLField;

class MutationResolver extends FieldResolver
{
    /**
     * Generate a GraphQL type from a field.
     *
     * @return array
     */
    public function generate()
    {
        return GraphQLField::toArray([
            'args' => $this->getArgs()->toArray(),
            'type' => $this->getType(),
            'resolve' => $this->resolver,
        ]);
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
                $args = directives()->argMiddleware($arg)
                    ->reduce(function ($type, $middlware) use ($arg) {
                        $directive = collect($arg->directives)
                            ->first(function (DirectiveNode $directive) use ($middlware) {
                                return $directive->name->value === $middlware::name();
                            });

                        return $middlware->handle($arg, $directive, $type);
                    }, ['type' => NodeResolver::resolve($arg->type)]);

                return [$arg->name->value => $args];
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
