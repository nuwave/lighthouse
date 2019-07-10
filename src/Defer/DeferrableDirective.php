<?php

namespace Nuwave\Lighthouse\Defer;

use Closure;
use GraphQL\Error\Error;
use GraphQL\Language\AST\TypeNode;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Language\AST\NonNullTypeNode;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class DeferrableDirective extends BaseDirective implements Directive, FieldMiddleware
{
    const NAME = 'deferrable';
    const THE_DEFER_DIRECTIVE_CANNOT_BE_USED_ON_A_ROOT_MUTATION_FIELD = 'The @defer directive cannot be used on a root mutation field.';
    const THE_DEFER_DIRECTIVE_CANNOT_BE_USED_ON_A_NON_NULLABLE_FIELD = 'The @defer directive cannot be used on a Non-Nullable field.';

    /**
     * @var \Nuwave\Lighthouse\Defer\Defer
     */
    protected $defer;

    /**
     * @param  \Nuwave\Lighthouse\Defer\Defer  $defer
     * @return void
     */
    public function __construct(Defer $defer)
    {
        $this->defer = $defer;
    }

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return self::NAME;
    }

    /**
     * Resolve the field directive.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @param  \Closure  $next
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $previousResolver = $fieldValue->getResolver();
        $fieldType = $fieldValue->getField()->type;

        $fieldValue->setResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($previousResolver, $fieldType) {
                $wrappedResolver = function () use ($previousResolver, $root, $args, $context, $resolveInfo) {
                    return $previousResolver($root, $args, $context, $resolveInfo);
                };
                $path = implode('.', $resolveInfo->path);

                if ($this->shouldDefer($fieldType, $resolveInfo)) {
                    return $this->defer->defer($wrappedResolver, $path);
                }

                return $this->defer->isStreaming()
                    ? $this->defer->findOrResolve($wrappedResolver, $path)
                    : $previousResolver($root, $args, $context, $resolveInfo);
            }
        );

        return $next($fieldValue);
    }

    /**
     * Determine if field should be deferred.
     *
     * @param  \GraphQL\Language\AST\TypeNode  $fieldType
     * @param  \GraphQL\Type\Definition\ResolveInfo  $resolveInfo
     * @return bool
     *
     * @throws \GraphQL\Error\Error
     */
    protected function shouldDefer(TypeNode $fieldType, ResolveInfo $resolveInfo): bool
    {
        foreach ($resolveInfo->fieldNodes as $fieldNode) {
            $deferDirective = ASTHelper::directiveDefinition($fieldNode, 'defer');
            if (! $deferDirective) {
                return false;
            }

            if ($resolveInfo->parentType->name === 'Mutation') {
                throw new Error(self::THE_DEFER_DIRECTIVE_CANNOT_BE_USED_ON_A_ROOT_MUTATION_FIELD);
            }

            if (! ASTHelper::directiveArgValue($deferDirective, 'if', true)) {
                return false;
            }

            $skipDirective = ASTHelper::directiveDefinition($fieldNode, 'skip');
            if (
                $skipDirective
                && ASTHelper::directiveArgValue($skipDirective, 'if') === true
            ) {
                return false;
            }

            $includeDirective = ASTHelper::directiveDefinition($fieldNode, 'include');
            if (
                $includeDirective
                && ASTHelper::directiveArgValue($includeDirective, 'if') === false
            ) {
                return false;
            }
        }

        if ($fieldType instanceof NonNullTypeNode) {
            throw new Error(self::THE_DEFER_DIRECTIVE_CANNOT_BE_USED_ON_A_NON_NULLABLE_FIELD);
        }

        return true;
    }
}
