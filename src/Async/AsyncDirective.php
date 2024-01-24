<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Async;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class AsyncDirective extends BaseDirective implements FieldMiddleware, FieldManipulator
{
    public static function definition(): string
    {
        return <<<GRAPHQL
"Dispatches a mutation to be "
directive @async(
    "Name of the queue to dispatch the job on."
    queue: String
) on FIELD_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue): void
    {
        $fieldValue->wrapResolver(fn (callable $resolver): \Closure => function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver): bool {
            dispatch(static fn (): mixed => $resolver($root, $args, $context, $resolveInfo))
                ->onQueue($this->directiveArgValue('queue'));

            return true;
        });
    }

    public function manipulateFieldDefinition(DocumentAST &$documentAST, FieldDefinitionNode &$fieldDefinition, ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType): void
    {
        $parentName = $parentType->name->value;
        if ($parentName !== RootType::MUTATION) {
            $location = "{$parentName}.{$fieldDefinition->name->value}";
            throw new DefinitionException("The @async directive must only be used on fields of the root type mutation, found it on {$location}.");
        }
    }
}
