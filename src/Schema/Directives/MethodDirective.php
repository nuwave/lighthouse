<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class MethodDirective extends BaseDirective implements FieldResolver, DefinedDirective
{
    /** @var \GraphQL\Language\AST\FieldDefinitionNode */
    protected $definitionNode;

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
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

  """
  Pass the field arguments to the method, using the argument definition
  order from the schema to sort them before passing them along.

  @deprecated This behaviour will default to true in v5 and this setting will be removed.
  """
  passOrdered: Boolean = false
) on FIELD_DEFINITION
SDL;
    }

    /**
     * Resolve the field directive.
     */
    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        return $fieldValue->setResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) {
                /** @var string $method */
                $method = $this->directiveArgValue(
                    'name',
                    $this->nodeName()
                );

                // TODO always do this in v5
                if ($this->directiveArgValue('passOrdered')) {
                    $orderedArgs = [];
                    foreach ($this->definitionNode->arguments as $argDefinition) {
                        $orderedArgs [] = $args[$argDefinition->name->value] ?? null;
                    }

                    return $root->{$method}(...$orderedArgs);
                }

                return $root->{$method}($root, $args, $context, $resolveInfo);
            }
        );
    }
}
