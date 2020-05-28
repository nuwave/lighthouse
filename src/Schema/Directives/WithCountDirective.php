<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Execution\DataLoader\BatchLoader;
use Nuwave\Lighthouse\Execution\DataLoader\RelationCountBatchLoader;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class WithCountDirective extends RelationDirective implements FieldMiddleware, DefinedDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Eager-load a count of an Eloquent relation.
"""
directive @withCount(
  """
  Specify the relationship method name in the model class,
  if it is named different from the field in the schema.
  """
  relation: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) on FIELD_DEFINITION
SDL;
    }

    /**
     * Eager load a count of a relation on the parent instance.
     */
    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $resolver = $fieldValue->getResolver();

        return $next(
            $fieldValue->setResolver(
                function (Model $parent, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver): Deferred {
                    return new Deferred(function () use ($resolver, $parent, $args, $context, $resolveInfo) {
                        return $this->loader($resolveInfo)
                            ->load($parent->getKey(), ['parent' => $parent])
                            ->then(
                                function () use ($resolver, $parent, $args, $context, $resolveInfo) {
                                    return $resolver($parent, $args, $context, $resolveInfo);
                                }
                            );
                    });
                }
            )
        );
    }

    /**
     * Create an instance of RelationCountBatchLoader loader to apply counts.
     */
    protected function loader($resolveInfo)
    {
        return BatchLoader::instance( // @phpstan-ignore-line TODO remove when updating graphql-php
            RelationCountBatchLoader::class,
            $resolveInfo->path,
            [
                'relationName' => $this->relationName(),
                'decorateBuilder' => function ($query) use ($resolveInfo) {
                    $resolveInfo
                        ->argumentSet
                        ->enhanceBuilder(
                            $query,
                            $this->directiveArgValue('scopes', [])
                        );
                },
            ]
        );
    }

    /**
     * The the name of the relation to be counted.
     */
    protected function relationName(): string
    {
        $relation = $this->directiveArgValue('relation');

        if (! $relation && Str::endsWith($this->nodeName(), '_count')) {
            return str_replace('_count', '', $this->nodeName());
        }

        return "{$relation} as {$this->nodeName()}";
    }
}
