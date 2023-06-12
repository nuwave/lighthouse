<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Defer;

use GraphQL\Error\Error;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\TypeNode;
use GraphQL\Type\Definition\Directive;
use Nuwave\Lighthouse\ClientDirectives\ClientDirective;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class DeferrableDirective extends BaseDirective implements FieldMiddleware
{
    public const THE_DEFER_DIRECTIVE_CANNOT_BE_USED_ON_A_ROOT_MUTATION_FIELD = 'The @defer directive cannot be used on a root mutation field.';

    public const THE_DEFER_DIRECTIVE_CANNOT_BE_USED_ON_A_NON_NULLABLE_FIELD = 'The @defer directive cannot be used on a Non-Nullable field.';

    public const DEFER_DIRECTIVE_NAME = 'defer';

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Do not use this directive directly, it is automatically added to the schema
when using the defer extension.
"""
directive @deferrable on FIELD_DEFINITION
GRAPHQL;
    }

    public function __construct(
        protected Defer $defer,
    ) {}

    public function handleField(FieldValue $fieldValue): void
    {
        $fieldType = $fieldValue->getField()->type;

        $fieldValue->wrapResolver(fn (callable $resolver): \Closure => function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver, $fieldType) {
            $wrappedResolver = static fn (): mixed => $resolver($root, $args, $context, $resolveInfo);
            $path = implode('.', $resolveInfo->path);

            if ($this->shouldDefer($fieldType, $resolveInfo)) {
                return $this->defer->defer($wrappedResolver, $path);
            }

            return $this->defer->findOrResolve($wrappedResolver, $path);
        });
    }

    /** Determine if the field should be deferred. */
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
        if (in_array([Directive::IF_ARGUMENT_NAME => true], $skips, true)) {
            return false;
        }

        $includes = (new ClientDirective(Directive::INCLUDE_NAME))->forField($resolveInfo);

        return ! in_array([Directive::IF_ARGUMENT_NAME => false], $includes, true);
    }

    /** @param  array<array<string, mixed>|null>  $defers */
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
