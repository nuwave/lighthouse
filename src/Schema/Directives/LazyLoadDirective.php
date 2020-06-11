<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Collection;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class LazyLoadDirective extends BaseDirective implements DefinedDirective, FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Perform a [lazy eager load](https://laravel.com/docs/eloquent-relationships#lazy-eager-loading)
on the relations of a list of models.
"""
directive @lazyLoad(
    """
    The names of the relationship methods to load.
    """
    relations: [String!]!
) on FIELD_DEFINITION
SDL;
    }

    /**
     * Resolve the field directive.
     */
    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $relations = $this->directiveArgValue('relations', []);
        $resolver = $fieldValue->getResolver();

        return $next(
            $fieldValue->setResolver(
                function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver, $relations) {
                    /** @var \GraphQL\Deferred|\Illuminate\Database\Eloquent\Model $result */
                    $result = $resolver($root, $args, $context, $resolveInfo);

                    $result instanceof Deferred
                        ? $result->then(function (Collection &$items) use ($relations): Collection {
                            $items->load($relations);

                            return $items;
                        })
                        : $result->load($relations);

                    return $result;
                }
            )
        );
    }
}
