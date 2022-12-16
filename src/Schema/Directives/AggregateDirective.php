<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Execution\BatchLoader\RelationBatchLoader;
use Nuwave\Lighthouse\Execution\ModelsLoader\AggregateModelsLoader;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class AggregateDirective extends BaseDirective implements FieldResolver, FieldManipulator
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
  Mutually exclusive with `model` and `builder`.
  """
  relation: String

  """
  The model with the column to aggregate.
  Mutually exclusive with `relation` and `builder`.
  """
  model: String

  """
  Point to a function that provides a Query Builder instance.
  Consists of two parts: a class name and a method name, seperated by an `@` symbol.
  If you pass only a class name, the method name defaults to `__invoke`.
  Mutually exclusive with `relation` and `model`.
  """
  builder: String

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
        $relation = $this->directiveArgValue('relation');
        if (is_string($relation)) {
            $fieldValue->setResolver(function (Model $parent, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) {
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
                assert($relationBatchLoader instanceof RelationBatchLoader);

                return $relationBatchLoader->load($parent);
            });

            return $fieldValue;
        }

        $modelArg = $this->directiveArgValue('model');
        if (is_string($modelArg)) {
            $fieldValue->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($modelArg) {
                $query = $this->namespaceModelClass($modelArg)::query();
                assert($query instanceof EloquentBuilder);

                $this->makeBuilderDecorator($resolveInfo)($query);

                return $query->{$this->function()}($this->column());
            });

            return $fieldValue;
        }

        if ($this->directiveHasArgument('builder')) {
            $builderResolver = $this->getResolverFromArgument('builder');

            $fieldValue->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($builderResolver) {
                $query = $builderResolver($root, $args, $context, $resolveInfo);

                assert(
                    $query instanceof QueryBuilder || $query instanceof EloquentBuilder,
                    "The method referenced by the builder argument of the @{$this->name()} directive on {$this->nodeName()} must return a Builder."
                );

                $this->makeBuilderDecorator($resolveInfo)($query);

                return $query->{$this->function()}($this->column());
            });

            return $fieldValue;
        }

        throw new DefinitionException("One of the arguments `model`, `relation` or `builder` must be assigned to the '{$this->name()}' directive on '{$this->nodeName()}'.");
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

    public function manipulateFieldDefinition(DocumentAST &$documentAST, FieldDefinitionNode &$fieldDefinition, ObjectTypeDefinitionNode &$parentType)
    {
        $this->validateMutuallyExclusiveArguments(['relation', 'model', 'builder']);
    }
}
