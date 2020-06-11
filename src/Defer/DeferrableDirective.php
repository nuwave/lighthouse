<?php

namespace Nuwave\Lighthouse\Defer;

use Closure;
use GraphQL\Error\Error;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\TypeNode;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\ClientDirectives\ClientDirective;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class DeferrableDirective extends BaseDirective implements DefinedDirective, FieldMiddleware
{
    public const THE_DEFER_DIRECTIVE_CANNOT_BE_USED_ON_A_ROOT_MUTATION_FIELD = 'The @defer directive cannot be used on a root mutation field.';
    public const THE_DEFER_DIRECTIVE_CANNOT_BE_USED_ON_A_NON_NULLABLE_FIELD = 'The @defer directive cannot be used on a Non-Nullable field.';
    public const DEFER_DIRECTIVE_NAME = 'defer';

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Do not use this directive directly, it is automatically added to the schema
when using the defer extension.
"""
directive @deferrable on FIELD_DEFINITION
SDL;
    }

    /**
     * @var \Nuwave\Lighthouse\Defer\Defer
     */
    protected $defer;

    public function __construct(Defer $defer)
    {
        $this->defer = $defer;
    }

    /**
     * Resolve the field directive.
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
     * @throws \GraphQL\Error\Error
     */
    protected function shouldDefer(TypeNode $fieldType, ResolveInfo $resolveInfo): bool
    {
        $defers = (new ClientDirective(self::DEFER_DIRECTIVE_NAME))->forField($resolveInfo);

        if ($this->anyFieldHasDefer($defers)) {
            if ($resolveInfo->parentType->name === RootType::MUTATION) {
                throw new Error(self::THE_DEFER_DIRECTIVE_CANNOT_BE_USED_ON_A_ROOT_MUTATION_FIELD);
            }
            if ($fieldType instanceof NonNullTypeNode) {
                throw new Error(self::THE_DEFER_DIRECTIVE_CANNOT_BE_USED_ON_A_NON_NULLABLE_FIELD);
            }
        }

        // Following the semantics of Apollo:
        // All declarations of a field have to contain @defer for the field to be deferred
        foreach ($defers as $defer) {
            if ($defer === null || $defer === [Directive::IF_ARGUMENT_NAME => false]) {
                return false;
            }
        }

        $skips = (new ClientDirective(Directive::SKIP_NAME))->forField($resolveInfo);
        foreach ($skips as $skip) {
            if ($skip === [Directive::IF_ARGUMENT_NAME => true]) {
                return false;
            }
        }

        $includes = (new ClientDirective(Directive::INCLUDE_NAME))->forField($resolveInfo);
        foreach ($includes as $include) {
            if ($include === [Directive::IF_ARGUMENT_NAME => false]) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<array<string, mixed>|null>  $defers
     */
    protected function anyFieldHasDefer(array $defers): bool
    {
        foreach ($defers as $defer) {
            if ($defer !== null) {
                return true;
            }
        }

        return false;
    }
}
