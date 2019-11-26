<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class RenameDirective extends BaseDirective implements FieldResolver, DefinedDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'rename';
    }

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'SDL'
directive @rename(
  """
  Specify the original name of the property/key that the field
  value can be retrieved from.
  """
  attribute: String!
) on FIELD_DEFINITION | INPUT_FIELD_DEFINITION
SDL;
    }

    /**
     * Resolve the field directive.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DirectiveException
     */
    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        $attribute = $this->getAttribute();

        return $fieldValue->setResolver(
            function ($rootValue) use ($attribute) {
                return data_get($rootValue, $attribute);
            }
        );
    }

    /**
     * Retrieves the attribute argument for the directive
     *
     * @return string
     *
     * @throws DirectiveException
     */
    public function getAttribute(): string
    {
        $attribute = $this->directiveArgValue('attribute');

        if (! $attribute) {
            throw new DirectiveException(
                "The [{$this->name()}] directive requires an `attribute` argument."
            );
        }

        return $attribute;
    }
}
