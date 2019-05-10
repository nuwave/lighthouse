<?php

namespace Nuwave\Lighthouse\Defer;

use Closure;
use GraphQL\Language\AST\TypeNode;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Language\AST\NonNullTypeNode;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Exceptions\ParseClientException;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class DeferrableDirective extends BaseDirective implements Directive, FieldMiddleware
{
    const NAME = 'deferrable';

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
     * @throws \Nuwave\Lighthouse\Exceptions\ParseClientException
     */
    protected function shouldDefer(TypeNode $fieldType, ResolveInfo $resolveInfo): bool
    {
        if (strtolower($resolveInfo->operation->operation) === 'mutation') {
            return false;
        }

        foreach ($resolveInfo->fieldNodes as $fieldNode) {
            $deferDirective = ASTHelper::directiveDefinition($fieldNode, 'defer');

            if (! $deferDirective) {
                return false;
            }

            if (! ASTHelper::directiveArgValue($deferDirective, 'if', true)) {
                return false;
            }

            $skipDirective = ASTHelper::directiveDefinition($fieldNode, 'skip');
            $includeDirective = ASTHelper::directiveDefinition($fieldNode, 'include');

            $shouldSkip = $skipDirective
                ? ASTHelper::directiveArgValue($skipDirective, 'if', false)
                : false;
            $shouldInclude = $includeDirective
                ? ASTHelper::directiveArgValue($includeDirective, 'if', false)
                : false;

            if ($shouldSkip || $shouldInclude) {
                return false;
            }
        }

        if ($fieldType instanceof NonNullTypeNode) {
            throw new ParseClientException('The @defer directive cannot be placed on a Non-Nullable field.');
        }

        return true;
    }
}
