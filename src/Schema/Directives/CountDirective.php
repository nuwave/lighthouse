<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Execution\BatchLoader\RelationBatchLoader;
use Nuwave\Lighthouse\Execution\ModelsLoader\CountModelsLoader;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CountDirective extends BaseDirective implements FieldResolver
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
  Mutually exclusive with the `model` argument.
  """
  relation: String

  """
  The model to count.
  Mutually exclusive with the `relation` argument.
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

    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        $modelArg = $this->directiveArgValue('model');
        if (is_string($modelArg)) {
            $fieldValue->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($modelArg): int {
                $query = $this
                    ->namespaceModelClass($modelArg)::query();

                $this->makeBuilderDecorator($resolveInfo)($query);

                if ($this->directiveArgValue('distinct')) {
                    $query->distinct();
                }

                $columns = $this->directiveArgValue('columns');
                if ($columns) {
                    return $query->count(...$columns);
                }

                return $query->count();
            });

            return $fieldValue;
        }

        $relation = $this->directiveArgValue('relation');
        if (is_string($relation)) {
            $fieldValue->setResolver(function (Model $parent, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) {
                /** @var \Nuwave\Lighthouse\Execution\BatchLoader\RelationBatchLoader $relationBatchLoader */
                $relationBatchLoader = BatchLoaderRegistry::instance(
                    array_merge(
                        $this->qualifyPath($args, $resolveInfo),
                        ['count']
                    ),
                    function () use ($resolveInfo): RelationBatchLoader {
                        return new RelationBatchLoader(
                            new CountModelsLoader($this->relation(), $this->makeBuilderDecorator($resolveInfo))
                        );
                    }
                );

                return $relationBatchLoader->load($parent);
            });

            return $fieldValue;
        }

        throw new DefinitionException(
            "A `model` or `relation` argument must be assigned to the '{$this->name()}' directive on '{$this->nodeName()}'."
        );
    }
}
