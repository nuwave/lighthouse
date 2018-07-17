<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Execution\NestedMutationExecutor;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;

class CreateNestedDirective extends BaseDirective implements FieldResolver
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'createNested';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     *
     * @return FieldValue
     * @throws DirectiveException
     */
    public function resolveField(FieldValue $value)
    {
        $modelClassName = $this->getModelClass();
        $model = new $modelClassName();

        return $value->setResolver(function ($root, $args) use ($model) {
            return NestedMutationExecutor::executeCreate($model, collect($args['input']));
        });
    }
}
