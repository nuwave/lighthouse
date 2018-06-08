<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Type\Definition\IDType;
use Nuwave\Lighthouse\Schema\Resolvers\NodeResolver;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;
use Nuwave\Lighthouse\Support\Traits\HandlesGlobalId;

class DeleteDirective implements FieldResolver
{
    use HandlesDirectives, HandlesGlobalId;

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
     * @return FieldValue
     */
    public function resolveField(FieldValue $value)
    {
        $idArg = $this->getIDField($value);
        $class = $this->directiveArgValue(
            $this->fieldDirective($value->getField(), self::name()),
            'model'
        );

        $globalId = $this->directiveArgValue(
            $this->fieldDirective($value->getField(), self::name()),
            'globalId',
            false
        );

        if (! $class) {
            throw new DirectiveException(sprintf(
                'The `delete` directive on %s [%s] must have a `model` argument',
                $value->getNodeName(),
                $value->getFieldName()
            ));
        }

        if (! $idArg) {
            new DirectiveException(sprintf(
                'The `delete` requires that you have an `ID` field on %s',
                $value->getNodeName()
            ));
        }

        return $value->setResolver(function ($root, array $args) use ($class, $idArg, $globalId) {
            $id = $globalId ? $this->decodeGlobalId(array_get($args, $idArg))[1] : array_get($args, $idArg);
            $model = $class::find($id);

            if ($model) {
                $model->delete();
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
