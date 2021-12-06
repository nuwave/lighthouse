<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class MethodDirective extends BaseDirective implements FieldResolver
{
    /** @var \GraphQL\Language\AST\FieldDefinitionNode */
    protected $definitionNode;

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Resolve a field by calling a method on the parent object.

Use this if the data is not accessible through simple property access or if you
want to pass argument to the method.
"""
directive @method(
  """
  Specify the method of which to fetch the data from.
  Defaults to the name of the field if not given.
  """
  name: String
) on FIELD_DEFINITION
GRAPHQL;
    }

    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        return $fieldValue->setResolver(
            /**
             * @param  array<string, mixed>  $args
             * @return mixed Really anything
             */
            function ($root, array $args) {
                /** @var string $method */
                $method = $this->directiveArgValue(
                    'name',
                    $this->nodeName()
                );

                $orderedArgs = [];
                foreach ($this->definitionNode->arguments as $argDefinition) {
                    $orderedArgs [] = $args[$argDefinition->name->value] ?? null;
                }

                return $root->{$method}(...$orderedArgs);
            }
        );
    }
}
