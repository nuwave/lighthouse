<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Type\Definition\IDType;
use Nuwave\Lighthouse\Schema\Resolvers\NodeResolver;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class UpdateDirective implements FieldResolver
{
    use HandlesDirectives;

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
    public function handle(FieldValue $value)
    {
        $idArg = $this->getIDField($value);
        $class = $this->directiveArgValue(
            $this->fieldDirective($value->getField(), $this->name()),
            'model'
        );

        $globalId = $this->directiveArgValue(
            $this->fieldDirective($value->getField(), $this->name()),
            'globalId',
            false
        );

        if (! $class) {
            throw new DirectiveException(sprintf(
                'The `update` directive on %s [%s] must have a `model` argument',
                $value->getNodeName(),
                $value->getFieldName()
            ));
        }

        if (! $idArg) {
            new DirectiveException(sprintf(
                'The `update` requires that you have an `ID` field on %s',
                $value->getNodeName()
            ));
        }

        return $value->setResolver(function ($root, array $args) use ($class, $idArg, $globalId) {
            $id = $globalId ? $this->decodeGlobalId(array_get($args, $idArg)) : array_get($args, $idArg);
            $model = $class::find($id);

            if ($model) {
                $attrs = collect($args)->except([$idArg])->toArray();
                $model->fill($attrs);
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
