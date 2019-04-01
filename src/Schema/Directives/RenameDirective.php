<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class RenameDirective extends BaseDirective implements FieldResolver
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
        $attribute = $this->directiveArgValue('attribute');

        if (! $attribute) {
            throw new DirectiveException(
                "The [{$this->name()}] directive requires an `attribute` argument."
            );
        }

        return $fieldValue->setResolver(
            function ($rootValue) use ($attribute) {
                return data_get($rootValue, $attribute);
            }
        );
    }
}
