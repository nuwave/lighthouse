<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Deferred;
use Illuminate\Database\Eloquent\Model;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Execution\DataLoader\BatchLoader;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Execution\DataLoader\RelationBatchLoader;

class WithDirective extends RelationDirective implements FieldMiddleware, DefinedDirective
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'with';
    }

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'SDL'
"""
Eager-load an Eloquent relation.
"""
directive @with(
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
     * Eager load a relation on the parent instance.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @param  \Closure  $next
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $resolver = $fieldValue->getResolver();

        return $next(
            $fieldValue->setResolver(
                function (Model $parent, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver): Deferred {
                    $loader = BatchLoader::instance(
                        RelationBatchLoader::class,
                        $resolveInfo->path,
                        [
                            'relationName' => $this->directiveArgValue('relation', $this->definitionNode->name->value),
                            'args' => $args,
                            'scopes' => $this->directiveArgValue('scopes', []),
                            'resolveInfo' => $resolveInfo,
                        ]
                    );

                    return new Deferred(function () use ($loader, $resolver, $parent, $args, $context, $resolveInfo) {
                        return $loader
                            ->load(
                                $parent->getKey(),
                                ['parent' => $parent]
                            )
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
}
