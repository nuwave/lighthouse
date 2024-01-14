<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Auth;

use Illuminate\Contracts\Auth\Access\Gate;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Utils;

abstract class BaseCanDirective extends BaseDirective implements FieldMiddleware
{
    public function __construct(
        protected Gate $gate,
    ) {}

    protected static function commonArguments(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
  """
  The ability to check permissions for.
  """
  ability: String!

  """
  Pass along the client given input data as arguments to `Gate::check`.
  """
  injectArgs: Boolean! = false

  """
  Statically defined arguments that are passed to `Gate::check`.

  You may pass arbitrary GraphQL literals,
  e.g.: [1, 2, 3] or { foo: "bar" }
  """
  args: CanArgs

  """
  Action to do if the user is not authorized.
  """
  action: CanAction! = EXCEPTION_PASS

  """
  Value to return if the user is not authorized and `action` is `RETURN_VALUE`.
  """
  returnValue: CanArgs
GRAPHQL;
    }

    protected static function commonTypes(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Any constant literal value: https://graphql.github.io/graphql-spec/draft/#sec-Input-Values
"""
scalar CanArgs

enum CanAction {
    """
    Pass exception to the client.
    """
    EXCEPTION_PASS

    """
    Throw generic "not authorized" exception to conceal the real error.
    """
    EXCEPTION_NOT_AUTHORIZED

    """
    Return the value specified in `value` argument to conceal the real error.
    """
    RETURN_VALUE
}
GRAPHQL;
    }

    /** Ensure the user is authorized to access this field. */
    public function handleField(FieldValue $fieldValue): void
    {
        $ability = $this->directiveArgValue('ability');

        $fieldValue->wrapResolver(fn (callable $resolver): \Closure => function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver, $ability) {
            $gate = $this->gate->forUser($context->user());
            $checkArguments = $this->buildCheckArguments($args);
            $authorizeModel = fn (mixed $model) => $this->authorizeModel($gate, $ability, $model, $checkArguments);

            try {
                return $this->authorizeRequest($root, $args, $context, $resolveInfo, $resolver, $authorizeModel);
            } catch (\Throwable $throwable) {
                $action = $this->directiveArgValue('action');
                if ($action === 'EXCEPTION_NOT_AUTHORIZED') {
                    throw new AuthorizationException();
                }

                if ($action === 'RETURN_VALUE') {
                    return $this->directiveArgValue('returnValue');
                }

                throw $throwable;
            }
        });
    }

    /**
     * Authorizes request and resolves the field.
     *
     * @phpstan-import-type Resolver from \Nuwave\Lighthouse\Schema\Values\FieldValue as Resolver
     *
     * @param  array<string, mixed>  $args
     * @param  callable(mixed, array<string, mixed>, \Nuwave\Lighthouse\Support\Contracts\GraphQLContext, \Nuwave\Lighthouse\Execution\ResolveInfo): mixed  $resolver
     * @param  callable(mixed): void  $authorize
     */
    abstract protected function authorizeRequest(mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo, callable $resolver, callable $authorize): mixed;

    /**
     * @param  string|array<string>  $ability
     * @param  array<int, mixed>  $arguments
     */
    protected function authorizeModel(Gate $gate, string|array $ability, mixed $model, array $arguments): void
    {
        // The signature of the second argument `$arguments` of `Gate::check`
        // should be [modelClassName, additionalArg, additionalArg...]
        array_unshift($arguments, $model);

        Utils::applyEach(
            static function ($ability) use ($gate, $arguments): void {
                $response = $gate->inspect($ability, $arguments);
                if ($response->denied()) {
                    throw new AuthorizationException($response->message(), $response->code());
                }
            },
            $ability,
        );
    }

    /**
     * Additional arguments that are passed to @see Gate::check().
     *
     * @param  array<string, mixed>  $args
     *
     * @return array<int, mixed>
     */
    protected function buildCheckArguments(array $args): array
    {
        $checkArguments = [];

        // The injected args come before the static args
        if ($this->directiveArgValue('injectArgs')) {
            $checkArguments[] = $args;
        }

        if ($this->directiveHasArgument('args')) {
            $checkArguments[] = $this->directiveArgValue('args');
        }

        return $checkArguments;
    }
}
