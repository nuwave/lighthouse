<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\SoftDeletes\TrashedDirective;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CanDirective extends BaseDirective implements FieldMiddleware, DefinedDirective
{
    /**
     * @var \Illuminate\Contracts\Auth\Access\Gate
     */
    protected $gate;

    /**
     * CanDirective constructor.
     * @param  \Illuminate\Contracts\Auth\Access\Gate  $gate
     * @return void
     */
    public function __construct(Gate $gate)
    {
        $this->gate = $gate;
    }

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
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
  The name of the argument that is used to find a specific model
  instance against which the permissions should be checked.
  """
  find: String

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
) on FIELD_DEFINITION
SDL;
    }

    /**
     * Ensure the user is authorized to access this field.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @param  \Closure  $next
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $previousResolver = $fieldValue->getResolver();

        return $next(
            $fieldValue->setResolver(
                function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($previousResolver) {
                    $gate = $this->gate->forUser($context->user());
                    $ability = $this->directiveArgValue('ability');
                    $checkArguments = $this->buildCheckArguments($args);

                    foreach ($this->modelsToCheck($resolveInfo->argumentSet, $args) as $model) {
                        $this->authorize($gate, $ability, $model, $checkArguments);
                    }

                    return $previousResolver($root, $args, $context, $resolveInfo);
                }
            )
        );
    }

    /**
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet  $argumentSet
     * @param  array  $args
     * @return iterable<Model|string>
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    protected function modelsToCheck(ArgumentSet $argumentSet, array $args): iterable
    {
        if ($find = $this->directiveArgValue('find')) {
            $modelOrModels = $argumentSet
                ->enhanceBuilder(
                    $this->getModelClass()::query(),
                    [],
                    function (Directive $directive): bool {
                        return $directive instanceof TrashedDirective;
                    }
                )
                ->findOrFail($args[$find]);

            if ($modelOrModels instanceof Model) {
                $modelOrModels = [$modelOrModels];
            }

            return $modelOrModels;
        }

        return [$this->getModelClass()];
    }

    /**
     * @param  \Illuminate\Contracts\Auth\Access\Gate  $gate
     * @param  string|string[]  $ability
     * @param  string|\Illuminate\Database\Eloquent\Model  $model
     * @param  array  $arguments
     * @return void
     *
     * @throws \Nuwave\Lighthouse\Exceptions\AuthorizationException
     */
    protected function authorize(Gate $gate, $ability, $model, array $arguments): void
    {
        // The signature of the second argument `$arguments` of `Gate::check`
        // should be [modelClassName, additionalArg, additionalArg...]
        array_unshift($arguments, $model);

        if (! $gate->check($ability, $arguments)) {
            throw new AuthorizationException(
                "You are not authorized to access {$this->nodeName()}"
            );
        }
    }

    /**
     * Additional arguments that are passed to `Gate::check`.
     *
     * @param  array  $args
     * @return mixed[]
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
