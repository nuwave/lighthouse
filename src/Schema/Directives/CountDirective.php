<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\DataLoader\RelationCountFetcher;
use Nuwave\Lighthouse\Execution\DataLoader\RelationFetcher;
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

                    $query = $resolveInfo->argumentSet->enhanceBuilder(
                        $query,
                        $this->directiveArgValue('scopes')
                    );

                    return $query->count();
                }
            );
        }

        // Fetch the count by relation
        $relation = $this->directiveArgValue('relation');
        if (! is_null($relation)) {
            return $value->setResolver(
                $this->deferredRelationResolver(
                    function (Model $model) {
                        return $model->{$this->nodeName()};
                    }
                )
            );
        }

        throw new DefinitionException(
            "A `model` or `relation` argument must be assigned to the '{$this->name()}' directive on '{$this->nodeName()}'."
        );
    }

    protected function relationName(): string
    {
        $relation = $this->directiveArgValue('relation');
        if (! $relation) {
            throw new DefinitionException("You must specify the argument relation in the {$this->name()} directive on {$this->definitionNode->name->value}.");
        }

        return "{$relation} as {$this->nodeName()}";
    }

    protected function relationFetcher(ResolveInfo $resolveInfo): RelationFetcher
    {
        return new RelationCountFetcher(
            $this->decorateBuilder($resolveInfo)
        );
    }
}
