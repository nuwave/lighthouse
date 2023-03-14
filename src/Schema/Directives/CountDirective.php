<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Deferred;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Execution\BatchLoader\RelationBatchLoader;
use Nuwave\Lighthouse\Execution\ModelsLoader\CountModelsLoader;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CountDirective extends BaseDirective implements FieldResolver, FieldManipulator
{
    use RelationDirectiveHelpers;

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Returns the count of a given relationship or model.
"""
directive @count(
  """
  The relationship to count.
  Mutually exclusive with `model`.
  """
  relation: String

  """
  The model to count.
  Mutually exclusive with `relation`.
  """
  model: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]

  """
  Count only rows where the given columns are non-null.
  `*` counts every row.
  """
  columns: [String!]! = ["*"]

  """
  Should exclude duplicated rows?
  """
  distinct: Boolean! = false
) on FIELD_DEFINITION
GRAPHQL;
    }

    public function resolveField(FieldValue $fieldValue): callable
    {
        $modelArg = $this->directiveArgValue('model');
        if (is_string($modelArg)) {
            return function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($modelArg): int {
                $query = $this
                    ->namespaceModelClass($modelArg)::query();

                $this->makeBuilderDecorator($root, $args, $context, $resolveInfo)($query);

                if ($this->directiveArgValue('distinct')) {
                    $query->distinct();
                }

                $columns = $this->directiveArgValue('columns');
                if ($columns) {
                    return $query->count(...$columns);
                }

                return $query->count();
            };
        }

        $relation = $this->directiveArgValue('relation');
        if (is_string($relation)) {
            return function (Model $parent, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Deferred {
                $relationBatchLoader = BatchLoaderRegistry::instance(
                    [...$this->qualifyPath($args, $resolveInfo), 'count'],
                    fn (): RelationBatchLoader => new RelationBatchLoader(
                        new CountModelsLoader($this->relation(), $this->makeBuilderDecorator($parent, $args, $context, $resolveInfo)),
                    ),
                );

                return $relationBatchLoader->load($parent);
            };
        }

        throw new DefinitionException("A `model` or `relation` argument must be assigned to the '{$this->name()}' directive on '{$this->nodeName()}'.");
    }

    public function manipulateFieldDefinition(DocumentAST &$documentAST, FieldDefinitionNode &$fieldDefinition, ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType): void
    {
        $this->validateMutuallyExclusiveArguments(['model', 'relation']);
    }
}
