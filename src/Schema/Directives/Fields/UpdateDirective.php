<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Type\Definition\IDType;
use Nuwave\Lighthouse\Schema\Resolvers\NodeResolver;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;
use Nuwave\Lighthouse\Support\Traits\HandlesGlobalId;

class UpdateDirective extends AbstractFieldDirective implements FieldResolver
{
    use HandlesDirectives, HandlesGlobalId;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public static function name()
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
     * @return \Closure
     */
    public function resolveField(FieldValue $value)
    {
        $idArg = $this->getIDField();
        $class = $this->associatedArgValue('model');

        $globalId = $this->associatedArgValue('globalId', false);

        if (! $class) {
            throw new DirectiveException(sprintf(
                'The `%s` directive on %s [%s] must have a `model` argument',
                self::name(),
                $value->getParentTypeName(),
                $value->getFieldName()
            ));
        }

        if (! $idArg) {
            new DirectiveException(sprintf(
                'The `%s` requires that you have an `ID` field on %s',
                self::name(),
                $value->getParentTypeName()
            ));
        }

        return function ($root, array $args) use ($class, $idArg, $globalId) {
            $id = $globalId ? $this->decodeGlobalId(array_get($args, $idArg))[1] : array_get($args, $idArg);
            $model = $class::find($id);

            if ($model) {
                $attributes = collect($args)->except([$idArg])->toArray();
                $model->fill($attributes);
                $model->save();
            }

            return $model;
        };
    }

    /**
     * Check if field has an ID argument.
     *
     * @return bool
     */
    protected function getIDField()
    {
        return collect($this->fieldDefinition->arguments)->filter(function (InputValueDefinitionNode $arg) {
            $type = NodeResolver::resolve($arg->type);
            $type = method_exists($type, 'getWrappedType') ? $type->getWrappedType() : $type;

            return $type instanceof IDType;
        })->map(function ($arg) {
            return $arg->name->value;
        })->first();
    }
}
