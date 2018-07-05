<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;

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
     * @param FieldValue $value
     *
     * @return FieldValue
     * @throws DirectiveException
     */
    public function resolveField(FieldValue $value): FieldValue
    {
        $attribute = $this->directiveArgValue('attribute');

        if (!$attribute) {
            throw new DirectiveException(sprintf(
                'The [%s] directive requires an `attribute` argument.',
                $this->name()
            ));
        }

        return $value->setResolver(function ($parent, array $args) use ($attribute) {
            return data_get($parent, $attribute);
        });
    }
}
