<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class MethodDirective extends BaseDirective implements FieldResolver
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'method';
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
        $method = $this->directiveArgValue(
            'name',
            $this->definitionNode->name->value
        );

        return $fieldValue->setResolver(
            function ($root, array $args, $context = null, ResolveInfo $info = null) use ($method) {
                return call_user_func_array([$root, $method], [$args, $context, $info]);
            }
        );
    }
}
