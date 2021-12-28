<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class LazyLoadDirective extends BaseDirective implements FieldMiddleware, FieldManipulator
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Perform a [lazy eager load](https://laravel.com/docs/eloquent-relationships#lazy-eager-loading)
on the relations of a list of models.
"""
directive @lazyLoad(
  """
  The names of the relationship methods to load.
  """
  relations: [String!]!
) repeatable on FIELD_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $relations = $this->directiveArgValue('relations');

        $fieldValue->resultHandler(
            /**
             * @param  Collection|LengthAwarePaginator  $items
             *
             * @return Collection|LengthAwarePaginator
             */
            static function ($items) use ($relations) {
                $items->load($relations);

                return $items;
            }
        );

        return $next($fieldValue);
    }

    public function manipulateFieldDefinition(DocumentAST &$documentAST, FieldDefinitionNode &$fieldDefinition, ObjectTypeDefinitionNode &$parentType)
    {
        $relations = $this->directiveArgValue('relations');
        if (! is_array($relations) || 0 === count($relations)) {
            throw new DefinitionException(
                "Must specify non-empty list of relations in `@{$this->name()}` directive on `{$parentType->name->value}.{$fieldDefinition->name->value}`."
            );
        }
    }
}
