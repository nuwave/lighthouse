<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;

class CreateDirective extends AbstractFieldDirective implements FieldResolver
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public static function name()
    {
        return 'create';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     *
     * @return \Closure
     */
    public function resolveField(FieldValue $value)
    {
        // TODO: create a model registry so we can auto-resolve this.
        $model = $this->associatedArgValue('model');

        if (! $model) {
            throw new DirectiveException(sprintf(
                'The `create` directive on %s [%s] must have a `model` argument',
                $value->getParentTypeName(),
                $value->getFieldName()
            ));
        }

        return function ($root, array $args) use ($model) {
            return $model::create($args);
        };
    }
}
