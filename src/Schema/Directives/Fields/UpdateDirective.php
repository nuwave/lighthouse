<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Illuminate\Database\Eloquent\Model;
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
    public function name(): string
    {
        return 'update';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $fieldValue
     *
     * @return FieldValue
     */
    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        return $fieldValue->setResolver(
            function ($root, array $args) {
                $modelClassName = $this->getModelClass();
                /** @var Model $model */
                $model = new $modelClassName;

                $flatten = $this->directiveArgValue('flatten', false);
                $args = $flatten
                    ? reset($args)
                    : $args;

                if($this->directiveArgValue('globalId', false)){
                    $args['id'] = GlobalId::decodeId($args['id']);
                }

                return MutationExecutor::executeUpdate($model, collect($args));
            }
        );
    }
}
