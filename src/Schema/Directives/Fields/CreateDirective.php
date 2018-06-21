<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;

class CreateDirective extends BaseDirective implements FieldResolver
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'create';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     *
     * @return FieldValue
     */
    public function resolveField(FieldValue $value)
    {
        // TODO: create a model registry so we can auto-resolve this.
        $model = $this->directiveArgValue('model');

        if (!$model) {
            throw new DirectiveException(sprintf(
                'The `create` directive on %s [%s] must have a `model` argument',
                $value->getNodeName(),
                $value->getFieldName()
            ));
        }

        return $value->setResolver(function ($root, array $args) use ($model) {
            return $model::create($args);
        });
    }
}
