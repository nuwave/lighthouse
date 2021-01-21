<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\DataLoader\RelationCountLoader;
use Nuwave\Lighthouse\Execution\DataLoader\RelationLoader;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CountDirective extends WithRelationDirective implements FieldResolver
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Returns the count of a given relationship or model.
"""
directive @count(
  """
  The relationship which you want to run the count on.
  Mutually exclusive with the `model` argument.
  """
  relation: String

  """
  The model to run the count on.
  Mutually exclusive with the `relation` argument.
  """
  model: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) on FIELD_DEFINITION
GRAPHQL;
    }

    public function resolveField(FieldValue $value): FieldValue
    {
        $modelArg = $this->directiveArgValue('model');
        if (! is_null($modelArg)) {
            return $value->setResolver(
                function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($modelArg): int {
                    /** @var \Illuminate\Database\Eloquent\Builder $query */
                    $query = $this
                        ->namespaceModelClass($modelArg)
                        ::query();

                    $this->decorateBuilder($resolveInfo)($query);

                    return $query->count();
                }
            );
        }

        // Fetch the count by relation
        $relation = $this->directiveArgValue('relation');
        if (! is_null($relation)) {
            return $value->setResolver(
                function (Model $parent, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) {
                    return $this->loadRelation($parent, $resolveInfo);
                }
            );
        }

        throw new DefinitionException(
            "A `model` or `relation` argument must be assigned to the '{$this->name()}' directive on '{$this->nodeName()}'."
        );
    }

    protected function relationName(): string
    {
        /**
         * We only got to this point because we already know this argument is set.
         *
         * @var string $relation
         */
        $relation = $this->directiveArgValue('relation');

        return $relation;
    }

    protected function relationLoader(ResolveInfo $resolveInfo): RelationLoader
    {
        return new RelationCountLoader(
            $this->decorateBuilder($resolveInfo)
        );
    }
}
