<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Type\Definition\IDType;
use Nuwave\Lighthouse\Schema\Resolvers\NodeResolver;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\HandlesGlobalId;

class DeleteDirective extends AbstractFieldDirective implements FieldResolver
{
    use HandlesGlobalId;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public static function name()
    {
        return 'delete';
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
        $idArg = $this->getIDField($value);
        $class = $this->associatedArgValue('model');

        $globalId = $this->associatedArgValue('globalId', false);

        if (! $class) {
            throw new DirectiveException(sprintf(
                'The `delete` directive on %s [%s] must have a `model` argument',
                $value->getParentTypeName(),
                $value->getFieldName()
            ));
        }

        if (! $idArg) {
            new DirectiveException(sprintf(
                'The `delete` requires that you have an `ID` field on %s',
                $value->getParentTypeName()
            ));
        }

        return function ($root, array $args) use ($class, $idArg, $globalId) {
            $id = $globalId ? $this->decodeGlobalId(array_get($args, $idArg))[1] : array_get($args, $idArg);
            $model = $class::find($id);

            if ($model) {
                $model->delete();
            }

            return $model;
        };
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
        return collect($this->fieldDefinition->arguments)->filter(function ($arg) {
            $type = NodeResolver::resolve($arg->type);
            $type = method_exists($type, 'getWrappedType') ? $type->getWrappedType() : $type;

            return $type instanceof IDType;
        })->map(function ($arg) {
            return $arg->name->value;
        })->first();
    }
}
