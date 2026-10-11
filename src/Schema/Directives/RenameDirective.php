<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class RenameDirective extends BaseDirective implements FieldResolver
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Change the internally used name of a field or argument.

This does not change the schema from a client perspective.
"""
directive @rename(
  """
  The internal name of an attribute/property/key.
  """
  attribute: String!
) on FIELD_DEFINITION | ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    public function resolveField(FieldValue $fieldValue): callable
    {
        $attribute = $this->attributeArgValue();

        return static fn (mixed $root): mixed => data_get($root, $attribute);
    }

    /** Retrieves the attribute argument for the directive. */
    public function attributeArgValue(): string
    {
        return $this->directiveArgValue('attribute')
            ?: throw new DefinitionException("The @{$this->name()} directive requires an `attribute` argument.");
    }
}
