<?php

namespace Nuwave\Lighthouse\Auth;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldBuilderDirective;

class WhereAuthDirective extends BaseDirective implements FieldBuilderDirective
{
    /**
     * @var \Illuminate\Contracts\Auth\Factory
     */
    protected $authFactory;

    public function __construct(AuthFactory $authFactory)
    {
        $this->authFactory = $authFactory;
    }

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
  Specify which guard to use, e.g. "api".
  When not defined, the default from `lighthouse.php` is used.
  """
  guard: String
) on FIELD_DEFINITION
GRAPHQL;
    }

    public function handleFieldBuilder(object $builder): object
    {
        assert($builder instanceof EloquentBuilder);

        // @phpstan-ignore-next-line Mixins are magic
        return $builder->whereHas(
            $this->directiveArgValue('relation'),
            function (object $query): void {
                assert($query instanceof EloquentBuilder);

                $guard = $this->directiveArgValue('guard', AuthServiceProvider::guard());

                $userId = $this
                    ->authFactory
                    ->guard($guard)
                    ->id();

                $query->whereKey($userId);
            }
        );
    }
}
