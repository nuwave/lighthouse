<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Support\Helper;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class FieldDirective extends BaseDirective implements FieldResolver
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'field';
    }

    /**
     * Resolve the field directive.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        [$className, $methodName] = $this->getMethodArgumentParts('resolver');

        $namespacedClassName = $this->namespaceClassName(
            $className,
            $fieldValue->defaultNamespacesForParent()
        );

        $resolver = Helper::constructResolver($namespacedClassName, $methodName);

        $additionalData = $this->directiveArgValue('args');

        return $fieldValue->setResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $info) use ($resolver, $additionalData) {
                return $resolver(
                    $root,
                    array_merge($args, ['directive' => $additionalData]),
                    $context,
                    $info
                );
            }
        );
    }
}
