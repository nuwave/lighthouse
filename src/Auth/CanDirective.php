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
use Nuwave\Lighthouse\Exceptions\ClientSafeModelNotFoundException;
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

/** @deprecated TODO remove with v7 */
class CanDirective extends BaseDirective implements FieldMiddleware, FieldManipulator
{
    public function __construct(
        protected Gate $gate,
    ) {}

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
  Check the policy against the model instances returned by the field resolver.
  Only use this if the field does not mutate data, it is run before checking.

  Mutually exclusive with `query`, `find`, and `root`.
  """
  resolved: Boolean! = false

  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.
  """
  model: String

  """
  Pass along the client given input data as arguments to `Gate::check`.
  """
  injectArgs: Boolean! = false

  """
  Statically defined arguments that are passed to `Gate::check`.

  You may pass arbitrary GraphQL literals,
  e.g.: [1, 2, 3] or { foo: "bar" }

  CanArgs pseudo-scalar is defined in BaseCanDirective.
  """
  args: CanArgs

  """
  Query for specific model instances to check the policy against, using arguments
  with directives that add constraints to the query builder, such as `@eq`.

  Mutually exclusive with `resolved`, `find`, and `root`.
  """
  query: Boolean! = false

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]

  """
  If your policy checks against specific model instances, specify
  the name of the field argument that contains its primary key(s).

  You may pass the string in dot notation to use nested inputs.

  Mutually exclusive with `resolved`, `query`, and `root`.
  """
  find: String

  """
  Should the query fail when the models of `find` were not found?
  """
  findOrFail: Boolean! = true

  """
  If your policy should check against the root value.

  Mutually exclusive with `resolved`, `query`, and `find`.
  """
  root: Boolean! = false
) repeatable on FIELD_DEFINITION
GRAPHQL;
    }

    /** Ensure the user is authorized to access this field. */
    public function handleField(FieldValue $fieldValue): void
    {
        $ability = $this->directiveArgValue('ability');
        $resolved = $this->directiveArgValue('resolved');

        $fieldValue->wrapResolver(fn (callable $resolver): \Closure => function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver, $ability, $resolved) {
            $gate = $this->gate->forUser($context->user());
            $checkArguments = $this->buildCheckArguments($args);

            if ($resolved) {
                return Resolved::handle(
                    $resolver($root, $args, $context, $resolveInfo),
                    function ($modelLike) use ($gate, $ability, $checkArguments) {
                        $modelOrModels = $modelLike instanceof Paginator
                            ? $modelLike->items()
                            : $modelLike;

                        Utils::applyEach(function (?Model $model) use ($gate, $ability, $checkArguments): void {
                            $this->authorize($gate, $ability, $model, $checkArguments);
                        }, $modelOrModels);

                        return $modelLike;
                    },
                );
            }

            foreach ($this->modelsToCheck($root, $args, $context, $resolveInfo) as $model) {
                $this->authorize($gate, $ability, $model, $checkArguments);
            }

            return $resolver($root, $args, $context, $resolveInfo);
        });
    }

    /**
     * @param  array<string, mixed>  $args
     *
     * @return iterable<\Illuminate\Database\Eloquent\Model|class-string<\Illuminate\Database\Eloquent\Model>>
     */
    protected function modelsToCheck(mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): iterable
    {
        if ($this->directiveArgValue('query')) {
            return $resolveInfo
                ->enhanceBuilder(
                    $this->getModelClass()::query(),
                    $this->directiveArgValue('scopes', []),
                    $root,
                    $args,
                    $context,
                    $resolveInfo,
                )
                ->get();
        }

        if ($this->directiveArgValue('root')) {
            return [$root];
        }

        if ($find = $this->directiveArgValue('find')) {
            $findValue = Arr::get($args, $find)
                ?? throw self::missingKeyToFindModel($find);

            $queryBuilder = $this->getModelClass()::query();

            $argumentSetDirectives = $resolveInfo->argumentSet->directives;
            $directivesContainsForceDelete = $argumentSetDirectives->contains(
                Utils::instanceofMatcher(ForceDeleteDirective::class),
            );
            if ($directivesContainsForceDelete) {
                /** @see \Illuminate\Database\Eloquent\SoftDeletes */
                // @phpstan-ignore-next-line because it involves mixins
                $queryBuilder->withTrashed();
            }

            $directivesContainsRestore = $argumentSetDirectives->contains(
                Utils::instanceofMatcher(RestoreDirective::class),
            );
            if ($directivesContainsRestore) {
                /** @see \Illuminate\Database\Eloquent\SoftDeletes */
                // @phpstan-ignore-next-line because it involves mixins
                $queryBuilder->onlyTrashed();
            }

            try {
                $enhancedBuilder = $resolveInfo->enhanceBuilder(
                    $queryBuilder,
                    $this->directiveArgValue('scopes', []),
                    $root,
                    $args,
                    $context,
                    $resolveInfo,
                    Utils::instanceofMatcher(TrashedDirective::class),
                );
                assert($enhancedBuilder instanceof EloquentBuilder);

                $modelOrModels = $this->directiveArgValue('findOrFail', true)
                    ? $enhancedBuilder->findOrFail($findValue)
                    : $enhancedBuilder->find($findValue);
            } catch (ModelNotFoundException $modelNotFoundException) {
                throw ClientSafeModelNotFoundException::fromLaravel($modelNotFoundException);
            }

            if ($modelOrModels instanceof Model) {
                return [$modelOrModels];
            }

            if ($modelOrModels === null) {
                return [];
            }

            return $modelOrModels;
        }

        return [$this->getModelClass()];
    }

    public static function missingKeyToFindModel(string $find): Error
    {
        return new Error("Got no key to find a model at the expected input path: {$find}.");
    }

    /**
     * @param  string|array<string>  $ability
     * @param  array<int, mixed>  $arguments
     */
    protected function authorize(Gate $gate, string|array $ability, string|Model|null $model, array $arguments): void
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

    public function manipulateFieldDefinition(DocumentAST &$documentAST, FieldDefinitionNode &$fieldDefinition, ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType): void
    {
        $this->validateMutuallyExclusiveArguments(['resolved', 'query', 'find', 'root']);

        if ($this->directiveHasArgument('resolved') && $parentType->name->value === RootType::MUTATION) {
            throw self::resolvedIsUnsafeInMutations($fieldDefinition->name->value);
        }
    }

    public static function resolvedIsUnsafeInMutations(string $fieldName): DefinitionException
    {
        return new DefinitionException("Do not use @can with `resolved` on mutation {$fieldName}, it is unsafe as the resolver will run before checking permissions. Use `query` or `find`.");
    }
}
