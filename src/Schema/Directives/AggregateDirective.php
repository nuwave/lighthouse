<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\ModelsLoader\AggregateModelsLoader;
use Nuwave\Lighthouse\Execution\ModelsLoader\ModelsLoader;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class AggregateDirective extends WithRelationDirective implements FieldResolver
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Returns an aggregate of a column in a given relationship or model.
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
}
GRAPHQL;
    }

    public function resolveField(FieldValue $value): FieldValue
    {
        $modelArg = $this->directiveArgValue('model');
        if (is_string($modelArg)) {
            return $value->setResolver(
                function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($modelArg) {
                    $query = $this
                        ->namespaceModelClass($modelArg)
                        ::query();

                    $this->makeBuilderDecorator($resolveInfo)($query);

                    return $query->aggregate($this->function(), [$this->column()]);
                }
            );
        }

        $relation = $this->directiveArgValue('relation');
        if (is_string($relation)) {
            return $value->setResolver(
                function (Model $parent, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) {
                    return $this->loadRelation($parent, $args, $resolveInfo);
                }
            );
        }

        throw new DefinitionException(
            "A `model` or `relation` argument must be assigned to the '{$this->name()}' directive on '{$this->nodeName()}'."
        );
    }

    protected function relation(): string
    {
        /**
         * We only got to this point because we already know this argument is set.
         *
         * @var string $relation
         */
        $relation = $this->directiveArgValue('relation');

        return $relation;
    }

    protected function relationLoader(ResolveInfo $resolveInfo): ModelsLoader
    {
        return new AggregateModelsLoader(
            $this->relation(),
            $this->column(),
            $this->function(),
            $this->makeBuilderDecorator($resolveInfo)
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

    protected function qualifyPath(array $path): array
    {
        return array_merge(
            parent::qualifyPath($path),
            [$this->column()]
        );
    }
}
