<?php

namespace Nuwave\Lighthouse\Auth;

use Closure;
use GraphQL\Error\Error;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\SoftDeletes\ForceDeleteDirective;
use Nuwave\Lighthouse\SoftDeletes\RestoreDirective;
use Nuwave\Lighthouse\SoftDeletes\TrashedDirective;
use Nuwave\Lighthouse\Support\AppVersion;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Utils;

class CanDirective extends BaseDirective implements FieldMiddleware, FieldManipulator
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
  Query for specific model instances to check the policy against, using arguments
  with directives that add constraints to the query builder, such as `@eq`.

  Mutually exclusive with `find`.
  """
  query: Boolean = false

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]

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
  args: CanArgs

  """
  If your policy checks against specific model instances, specify
  the name of the field argument that contains its primary key(s).

  You may pass the string in dot notation to use nested inputs.

  Mutually exclusive with `search`.
  """
  find: String
) repeatable on FIELD_DEFINITION

"""
Any constant literal value: https://graphql.github.io/graphql-spec/draft/#sec-Input-Values
"""
scalar CanArgs
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
     *
     * @throws \GraphQL\Error\Error
     *
     * @return iterable<\Illuminate\Database\Eloquent\Model|class-string<\Illuminate\Database\Eloquent\Model>>
     */
    protected function modelsToCheck(ArgumentSet $argumentSet, array $args): iterable
    {
        if ($this->directiveArgValue('query')) {
            return $argumentSet
                ->enhanceBuilder(
                    $this->getModelClass()::query(),
                    $this->directiveArgValue('scopes', [])
                )
                ->get();
        }

        if ($find = $this->directiveArgValue('find')) {
            $findValue = Arr::get($args, $find);
            if (null === $findValue) {
                throw new Error(self::missingKeyToFindModel($find));
            }

            $queryBuilder = $this->getModelClass()::query();

            $directivesContainsForceDelete = $argumentSet->directives->contains(
                Utils::instanceofMatcher(ForceDeleteDirective::class)
            );
            if ($directivesContainsForceDelete) {
                /** @see \Illuminate\Database\Eloquent\SoftDeletes */
                // @phpstan-ignore-next-line because it involves mixins
                $queryBuilder->withTrashed();
            }

            $directivesContainsRestore = $argumentSet->directives->contains(
                Utils::instanceofMatcher(RestoreDirective::class)
            );
            if ($directivesContainsRestore) {
                /** @see \Illuminate\Database\Eloquent\SoftDeletes */
                // @phpstan-ignore-next-line because it involves mixins
                $queryBuilder->onlyTrashed();
            }

            try {
                /**
                 * TODO use generics.
                 *
                 * @var \Illuminate\Database\Eloquent\Builder $enhancedBuilder
                 */
                $enhancedBuilder = $argumentSet->enhanceBuilder(
                    $queryBuilder,
                    $this->directiveArgValue('scopes', []),
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
        } elseif (! $gate->check($ability, $arguments)) {
            throw new AuthorizationException(
                "You are not authorized to access {$this->nodeName()}"
            );
        }
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

    public function manipulateFieldDefinition(DocumentAST &$documentAST, FieldDefinitionNode &$fieldDefinition, ObjectTypeDefinitionNode &$parentType)
    {
        if ($this->directiveHasArgument('find') && $this->directiveHasArgument('query')) {
            throw new DefinitionException(self::findAndQueryAreMutuallyExclusive());
        }
    }

    public static function findAndQueryAreMutuallyExclusive(): string
    {
        return 'The arguments `find` and `query` are mutually exclusive in the `@can` directive.';
    }
}
