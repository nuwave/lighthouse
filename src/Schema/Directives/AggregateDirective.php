<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Execution\BatchLoader\RelationBatchLoader;
use Nuwave\Lighthouse\Execution\ModelsLoader\AggregateModelsLoader;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class AggregateDirective extends BaseDirective implements FieldResolver
{
    use RelationDirectiveHelpers;

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Returns an aggregate of a column in a given relationship or model.

Requires Laravel 8+.
"""
directive @aggregate(
  """
  The column to aggregate.
  """
  column: String!

  """
  The aggregate function to compute.
  """
  function: AggregateFunction!

  """
  The relationship with the column to aggregate.
  Mutually exclusive with the `model` argument.
  """
  relation: String

  """
  The model with the column to aggregate.
  Mutually exclusive with the `relation` argument.
  """
  model: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) on FIELD_DEFINITION

"""
Options for the `function` argument of `@aggregate`.
"""
enum AggregateFunction {
    """
    Return the average value.
    """
    AVG

    """
    Return the sum.
    """
    SUM

    """
    Return the minimum.
    """
    MIN

    """
    Return the maximum.
    """
    MAX
}
GRAPHQL;
    }

    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        $modelArg = $this->directiveArgValue('model');
        if (is_string($modelArg)) {
            $fieldValue->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($modelArg) {
                /** @var Builder $query */
                $query = $this
                    ->namespaceModelClass($modelArg)::query();

                $this->makeBuilderDecorator($resolveInfo)($query);

                return $query->{$this->function()}($this->column());
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
                        [$this->function(), $this->column()]
                    ),
                    function () use ($resolveInfo): RelationBatchLoader {
                        return new RelationBatchLoader(
                            new AggregateModelsLoader(
                                $this->relation(),
                                $this->column(),
                                $this->function(),
                                $this->makeBuilderDecorator($resolveInfo)
                            )
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

    protected function function(): string
    {
        return strtolower(
            $this->directiveArgValue('function')
        );
    }

    protected function column(): string
    {
        return $this->directiveArgValue('column');
    }
}
