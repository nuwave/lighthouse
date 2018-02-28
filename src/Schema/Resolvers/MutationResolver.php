<?php

namespace Nuwave\Lighthouse\Schema\Resolvers;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Schema\Resolvers\NodeResolver;
use Nuwave\Lighthouse\Schema\Types\GraphQLField;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;

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
                $value = directives()->argMiddleware($arg)
                    ->reduce(function (ArgumentValue $value, $middleware) use ($arg) {
                        return $middleware->handle(
                            $value->setArg($arg)->setMiddlewareDirective($middleware->name())
                        );
                    }, ArgumentValue::init(
                        $this->field,
                        NodeResolver::resolve($arg->type)
                    ));

                return [$arg->name->value => $value->getValue()];
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
