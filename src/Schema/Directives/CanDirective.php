<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\SoftDeletes\ForceDeleteDirective;
use Nuwave\Lighthouse\SoftDeletes\RestoreDirective;
use Nuwave\Lighthouse\SoftDeletes\TrashedDirective;
use Nuwave\Lighthouse\Support\AppVersion;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Utils;

class CanDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * @var \Illuminate\Contracts\Auth\Access\Gate
     */
    protected $gate;

    public function __construct(Gate $gate)
    {
        $this->gate = $gate;
    }

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Check a Laravel Policy to ensure the current user is authorized to access a field.

When `injectArgs` and `args` are used together, the client given
arguments will be passed before the static args.
"""
directive @can(
  """
  The ability to check permissions for.
  """
  ability: String!

  """
  If your policy checks against specific model instances, specify
  the name of the field argument that contains its primary key(s).

  You may pass the string in dot notation to use nested inputs.
  """
  find: String

  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.
  """
  model: String

  """
  Pass along the client given input data as arguments to `Gate::check`.
  """
  injectArgs: Boolean = false

  """
  Statically defined arguments that are passed to `Gate::check`.

  You may pass pass arbitrary GraphQL literals,
  e.g.: [1, 2, 3] or { foo: "bar" }
  """
  args: Mixed
) repeatable on FIELD_DEFINITION
GRAPHQL;
    }

    /**
     * Ensure the user is authorized to access this field.
     */
    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $previousResolver = $fieldValue->getResolver();
        $ability = $this->directiveArgValue('ability');

        $fieldValue->setResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($ability, $previousResolver) {
                $gate = $this->gate->forUser($context->user());
                $checkArguments = $this->buildCheckArguments($args);

                foreach ($this->modelsToCheck($resolveInfo->argumentSet, $args) as $model) {
                    $this->authorize($gate, $ability, $model, $checkArguments);
                }

                return $previousResolver($root, $args, $context, $resolveInfo);
            }
        );

        return $next($fieldValue);
    }

    /**
     * @param  array<string, mixed>  $args
     * @return iterable<\Illuminate\Database\Eloquent\Model|string>
     *
     * @throws \GraphQL\Error\Error
     */
    protected function modelsToCheck(ArgumentSet $argumentSet, array $args): iterable
    {
        if ($find = $this->directiveArgValue('find')) {
            $findValue = Arr::get($args, $find);
            if ($findValue === null) {
                throw new Error(self::missingKeyToFindModel($find));
            }

            $queryBuilder = $this->getModelClass()::query();

            $directivesContainsForceDelete = $argumentSet->directives->contains(
                Utils::instanceofMatcher(ForceDeleteDirective::class)
            );
            if ($directivesContainsForceDelete) {
                /** @var \Illuminate\Database\Eloquent\Builder&\Illuminate\Database\Eloquent\SoftDeletes $queryBuilder */
                $queryBuilder->withTrashed();
            }

            $directivesContainsRestore = $argumentSet->directives->contains(
                Utils::instanceofMatcher(RestoreDirective::class)
            );
            if ($directivesContainsRestore) {
                /** @var \Illuminate\Database\Eloquent\Builder&\Illuminate\Database\Eloquent\SoftDeletes $queryBuilder */
                $queryBuilder->onlyTrashed();
            }

            try {
                /**
                 * TODO use generics.
                 * @var \Illuminate\Database\Eloquent\Builder $enhancedBuilder
                 */
                $enhancedBuilder = $argumentSet->enhanceBuilder(
                    $queryBuilder,
                    [],
                    Utils::instanceofMatcher(TrashedDirective::class)
                );

                $modelOrModels = $enhancedBuilder->findOrFail($findValue);
            } catch (ModelNotFoundException $exception) {
                throw new Error($exception->getMessage());
            }

            if ($modelOrModels instanceof Model) {
                $modelOrModels = [$modelOrModels];
            }

            return $modelOrModels;
        }

        return [$this->getModelClass()];
    }

    public static function missingKeyToFindModel(string $find): string
    {
        return "Got no key to find a model at the expected input path: ${find}.";
    }

    /**
     * @param  string|array<string>  $ability
     * @param  string|\Illuminate\Database\Eloquent\Model  $model
     * @param  array<int, mixed>  $arguments
     *
     * @throws \Nuwave\Lighthouse\Exceptions\AuthorizationException
     */
    protected function authorize(Gate $gate, $ability, $model, array $arguments): void
    {
        // The signature of the second argument `$arguments` of `Gate::check`
        // should be [modelClassName, additionalArg, additionalArg...]
        array_unshift($arguments, $model);

        // Gate responses were introduced in Laravel 6
        // TODO remove with Laravel < 6 support
        if (AppVersion::atLeast(6.0)) {
            Utils::applyEach(
                function ($ability) use ($gate, $arguments) {
                    $response = $gate->inspect($ability, $arguments);

                    if ($response->denied()) {
                        throw new AuthorizationException($response->message(), $response->code());
                    }
                },
                $ability
            );
        } else {
            if (! $gate->check($ability, $arguments)) {
                throw new AuthorizationException(
                    "You are not authorized to access {$this->nodeName()}"
                );
            }
        }
    }

    /**
     * Additional arguments that are passed to `Gate::check`.
     *
     * @param  array<string, mixed>  $args
     * @return array<int, mixed>
     */
    protected function buildCheckArguments(array $args): array
    {
        $checkArguments = [];

        // The injected args come before the static args
        if ($this->directiveArgValue('injectArgs')) {
            $checkArguments [] = $args;
        }

        if ($this->directiveHasArgument('args')) {
            $checkArguments [] = $this->directiveArgValue('args');
        }

        return $checkArguments;
    }
}
