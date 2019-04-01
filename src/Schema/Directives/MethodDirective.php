<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

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
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        $method = $this->directiveArgValue(
            'name',
            $this->definitionNode->name->value
        );

        return $fieldValue->setResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($method) {
                return call_user_func([$root, $method], $root, $args, $context, $resolveInfo);
            }
        );
    }
}
