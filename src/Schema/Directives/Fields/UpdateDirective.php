<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Execution\Utils\GlobalId;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Execution\MutationExecutor;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class UpdateDirective extends BaseDirective implements FieldResolver
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'update';
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
        return $value->setResolver(function ($root, $args) {
            $modelClassName = $this->getModelClass();
            $model = new $modelClassName;

            $flatten = $this->directiveArgValue('flatten', false);
            $args = $flatten
                ? reset($args)
                : $args;

            if($this->directiveArgValue('globalId', false)){
                $args['id'] = GlobalId::decodeId($args['id']);
            }

            return MutationExecutor::executeUpdate($model, collect($args));
        });
    }
}
