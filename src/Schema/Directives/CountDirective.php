<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Execution\DataLoader\RelationCountBatchLoader;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class CountDirective extends WithRelationDirective implements FieldResolver, DefinedDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Returns the count of a given relationship or model.
"""
directive @count(
  """
  The relationship which you want to run the count on.
  """
  relation: String

  """
  The model to run the count on.
  """
  model: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) on FIELD_DEFINITION
SDL;
    }

    /**
     * Returns the count of a given relationship or model.
     */
    public function resolveField(FieldValue $value): FieldValue
    {
        return $value->setResolver(
            $this->deferredRelationResolver(
                function (?Model $model) {
                    // Fetch the count by relation
                    $relation = $this->directiveArgValue('relation');
                    if (! is_null($relation)) {
                        return $model->{$this->nodeName()};
                    }

                    // Else we try to fetch by model.
                    $modelArg = $this->directiveArgValue('model');
                    if (! is_null($modelArg)) {
                        return $this->countModel($modelArg);
                    }

                    throw new DirectiveException(
                        "A `model` or `relation` argument must be assigned to the '{$this->name()}' directive on '{$this->nodeName()}"
                    );
                }
            )
        );
    }

    public function batchLoaderClass(): string
    {
        return RelationCountBatchLoader::class;
    }

    protected function countModel(string $modelName): int
    {
        $scopesArg = $this->directiveArgValue('scopes');

        return $this->namespaceModelClass($modelName)
            ::query()
            ->when(! is_null($scopesArg), function (Builder $query) use ($scopesArg) {
                return $query->scopes($scopesArg);
            })
            ->count();
    }

    public function relationName(): string
    {
        $relation = $this->directiveArgValue('relation');

        return "{$relation} as {$this->nodeName()}";
    }
}
