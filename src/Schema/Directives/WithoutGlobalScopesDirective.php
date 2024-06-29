<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirectiveForArray;

final class WithoutGlobalScopesDirective extends BaseDirective implements ArgDirectiveForArray, ArgBuilderDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
directive @withoutGlobalScopes(

  """
   names of the scopes on the custom query builder.
  """
names: [String!]
) on FIELD_DEFINITION
GRAPHQL;
    }

    /**
     * Add additional constraints to the builder based on the given argument value.
     *
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model> $builder the builder used to resolve the field
     * @param mixed $value the client given value of the argument
     *
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model> the modified builder
     */
    public function handleBuilder(QueryBuilder|EloquentBuilder|Relation $builder, mixed $value): QueryBuilder|EloquentBuilder|Relation
    {
        if (!$value) {
            return $builder;
        }
        $scopes = $this->directiveArgValue('names', $this->nodeName());

        try {
            return $builder->withoutGlobalScopes($scopes);

        } catch (\BadMethodCallException $badMethodCallException) {
            throw new DefinitionException(
                "{$badMethodCallException->getMessage()} in @{$this->name()} directive on {$this->nodeName()} argument.",
                $badMethodCallException->getCode(),
                $badMethodCallException->getPrevious(),
            );
        }
    }
}
