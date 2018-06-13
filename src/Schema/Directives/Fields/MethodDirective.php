<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;

class MethodDirective extends AbstractFieldDirective implements FieldResolver
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public static function name()
    {
        return 'method';
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
        $method = $this->associatedArgValue('name', $this->fieldDefinition->name->value);

        return function ($root, array $args, $context = null, ResolveInfo $info = null) use ($method) {
            return call_user_func_array([$root, $method], [$args, $context, $info]);
        };
    }
}
