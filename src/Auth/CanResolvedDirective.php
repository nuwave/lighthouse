<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Auth;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Contracts\Pagination\Paginator;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\Resolved;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Utils;

class CanResolvedDirective extends BaseCanDirective implements FieldManipulator
{
    public static function definition(): string
    {
        $commonArguments = BaseCanDirective::commonArguments();

        return /** @lang GraphQL */ <<<GRAPHQL
"""
Check a Laravel Policy to ensure the current user is authorized to access a field.

Check the policy against the model instances returned by the field resolver.
Only use this if the field does not mutate data, it is run before checking.
"""
directive @canResolved(
{$commonArguments}
) repeatable on FIELD_DEFINITION
GRAPHQL;
    }

    protected function authorizeRequest(mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo, callable $resolver, callable $authorize): mixed
    {
        return Resolved::handle(
            $resolver($root, $args, $context, $resolveInfo),
            static function (mixed $modelLike) use ($authorize) {
                $modelOrModels = $modelLike instanceof Paginator
                    ? $modelLike->items()
                    : $modelLike;
                Utils::applyEach(static function (mixed $model) use ($authorize): void {
                    $authorize($model);
                }, $modelOrModels);

                return $modelLike;
            },
        );
    }

    public function manipulateFieldDefinition(DocumentAST &$documentAST, FieldDefinitionNode &$fieldDefinition, ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType): void
    {
        if ($parentType->name->value === RootType::MUTATION) {
            throw new DefinitionException("Do not use @canResolved on mutation {$fieldDefinition->name->value}, it is unsafe as the resolver will run before checking permissions. Use @canFind or @canQuery instead.");
        }
    }
}
