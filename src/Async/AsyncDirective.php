<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Async;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Container\Container;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\SerializesContext;

class AsyncDirective extends BaseDirective implements FieldMiddleware, FieldManipulator
{
    public static function definition(): string
    {
        return <<<GRAPHQL
"""
Defer the execution of mutations to [queued jobs](https://laravel.com/docs/queues).

This directive must only be used on fields of the root mutation type.
When the field is executed, a `Nuwave\Lighthouse\Async\AsyncMutation` job is dispatched
and the value `true` is returned - thus the fields return type must be `Boolean!`.

Once a [queue worker](https://laravel.com/docs/queues#running-the-queue-worker) picks up the job,
it will actually execute the underlying field resolver.
The result is not checked for errors, ensure your GraphQL error handling reports relevant exceptions.
"""
directive @async(
    """
    Name of the queue to dispatch the job on.
    If not specified, jobs will be dispatched to the default queue.
    See https://laravel.com/docs/queues#customizing-the-queue-and-connection.
    """
    queue: String
) on FIELD_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue): void
    {
        $fieldValue->wrapResolver(fn (callable $resolver): \Closure => function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver): bool {
            if ($root instanceof AsyncRoot) {
                $resolver($root, $args, $context, $resolveInfo);
            } else {
                $contextSerializer = Container::getInstance()->make(SerializesContext::class);
                assert($contextSerializer instanceof SerializesContext);
                $serializedContext = $contextSerializer->serialize($context);

                AsyncMutation::dispatch(
                    $serializedContext,
                    $resolveInfo->fragments,
                    $resolveInfo->operation,
                    $resolveInfo->variableValues,
                )->onQueue($this->directiveArgValue('queue'));
            }

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
