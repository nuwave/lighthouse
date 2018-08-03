<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Type\Definition\IDType;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Resolvers\NodeResolver;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Schema\Execution\Utils\GlobalIdUtil;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;

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
     * @throws DirectiveException
     *
     * @return FieldValue
     */
    public function resolveField(FieldValue $value)
    {
        $idArg = $this->getIDField($value);
        $globalId = $this->directiveArgValue('globalId', false);

        if (! $idArg) {
            new DirectiveException(sprintf(
                'The `update` requires that you have an `ID` field on %s',
                $value->getNodeName()
            ));
        }

        return $value->setResolver(function ($root, array $args) use ($idArg, $globalId) {
            $id = $globalId ? GlobalIdUtil::decodeGlobalId(array_get($args, $idArg))[1] : array_get($args, $idArg);

            $model = $this->getModelClass()::find($id);

            if ($model) {
                $attributes = collect($args)->except([$idArg])->toArray();
                $model->fill($attributes);
                $model->save();
            }

            return $model;
        });
    }

    /**
     * Check if field has an ID argument.
     *
     * @param FieldValue $value
     *
     * @return bool
     */
    protected function getIDField(FieldValue $value)
    {
        return collect($value->getField()->arguments)->filter(function ($arg) {
            $type = NodeResolver::resolve($arg->type);
            $type = method_exists($type, 'getWrappedType') ? $type->getWrappedType() : $type;

            return $type instanceof IDType;
        })->map(function ($arg) {
            return $arg->name->value;
        })->first();
    }
}
