<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Auth;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\Resolved;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\SoftDeletes\ForceDeleteDirective;
use Nuwave\Lighthouse\SoftDeletes\RestoreDirective;
use Nuwave\Lighthouse\SoftDeletes\TrashedDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Utils;
use Throwable;

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
  args: CanArgs or : [CanArgs]

  """
  Action to do if the user is not authorized.
  """
  action: CanAction! = EXCEPTION_PASS

  """
  Value to return if the user is not authorized and `action` is `RETURN_VALUE`.
  """
  return_value: CanArgs or : [CanArgs]
GRAPHQL;
    }

    public static function definition(): string
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
            $authorizeModel = fn(string|object|array|null $model) => $this->authorizeModel($gate, $ability, $model, $checkArguments);

            try {
                return $this->authorizeRequest($root, $args, $context, $resolveInfo, $resolver, $authorizeModel);
            } catch (Throwable $e) {
                $action = $this->directiveArgValue('action');
                if ($action === 'EXCEPTION_NOT_AUTHORIZED'){
                    throw new AuthorizationException();
                }

                if ($action === 'RETURN_VALUE') {
                    return $this->directiveArgValue('return_value');
                }


                throw $e;
            }
        });
    }

    /**
     * Authorizes request and resolves the field
     *
     * @param array<string, mixed> $args
     * @throws \Nuwave\Lighthouse\Exceptions\AuthorizationException
     */
    protected abstract function authorizeRequest(mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo, callable $resolver, callable $authorize): mixed;

    /**
     * @param string|array<string> $ability
     * @param array<int, mixed> $arguments
     * @throws \Nuwave\Lighthouse\Exceptions\AuthorizationException
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
