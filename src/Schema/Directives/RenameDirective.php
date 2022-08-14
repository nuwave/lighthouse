<?php

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

    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        $attribute = $this->attributeArgValue();

        $fieldValue->setResolver(function ($rootValue) use ($attribute) {
            return data_get($rootValue, $attribute);
        });

        return $fieldValue;
    }

    /**
     * Retrieves the attribute argument for the directive.
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    public function attributeArgValue(): string
    {
        $attribute = $this->directiveArgValue('attribute');

        if (! $attribute) {
            throw new DefinitionException(
                "The @{$this->name()} directive requires an `attribute` argument."
            );
        }

        return $attribute;
    }
}
