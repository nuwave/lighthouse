<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Auth;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class WhereAuthDirective extends BaseDirective implements FieldBuilderDirective
{
    public function __construct(
        protected AuthFactory $authFactory,
    ) {}

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Filter a type to only return instances owned by the current user.
"""
directive @whereAuth(
  """
  Name of the relationship that links to the user model.
  """
  relation: String!

  """
  Specify which guards to use, e.g. ["api"].
  When not defined, the default from `lighthouse.php` is used.
  """
  guards: [String!]
) on FIELD_DEFINITION
GRAPHQL;
    }

    public function handleFieldBuilder(QueryBuilder|EloquentBuilder|Relation $builder, mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): QueryBuilder|EloquentBuilder|Relation
    {
        assert($builder instanceof EloquentBuilder);

        return $builder->whereHas(
            $this->directiveArgValue('relation'),
            function (object $query): void {
                assert($query instanceof EloquentBuilder);

                $guards = $this->directiveArgValue('guards', AuthServiceProvider::guards());
                $query->whereKey($this->authenticatedUserID($guards));
            },
        );
    }

    /**
     * Return the ID of the first logged-in user to any of the given guards.
     *
     * @param  array<string>  $guards
     */
    protected function authenticatedUserID(array $guards): int|string|null
    {
        foreach ($guards as $guard) {
            $id = $this->authFactory->guard($guard)
                ->id();
            if ($id !== null) {
                return $id;
            }
        }

        return null;
    }
}
