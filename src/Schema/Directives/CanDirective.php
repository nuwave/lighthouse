<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use Illuminate\Database\Eloquent\Model;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Contracts\Auth\Access\Gate;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;

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

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'can';
    }

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'SDL'
"""
Check a Laravel Policy to ensure the current user is authorized to access a field.
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
  Additional arguments that are passed to `Gate::check`. 
  """
  args: [String!]
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
                    $modelClass = $this->getModelClass();

                    if ($find = $this->directiveArgValue('find')) {
                        $modelOrModels = $modelClass::findOrFail($args[$find]);

                        if ($modelOrModels instanceof Model) {
                            $modelOrModels = [$modelOrModels];
                        }

                        /** @var \Illuminate\Database\Eloquent\Model $model */
                        foreach ($modelOrModels as $model) {
                            $this->authorize($context->user(), $model);
                        }
                    } else {
                        $this->authorize($context->user(), $modelClass);
                    }

                    return call_user_func_array($previousResolver, func_get_args());
                }
            )
        );
    }

    /**
     * @param  \Illuminate\Contracts\Auth\Authenticatable|null  $user
     * @param  string|\Illuminate\Database\Eloquent\Model  $model
     * @return void
     *
     * @throws \Nuwave\Lighthouse\Exceptions\AuthorizationException
     */
    protected function authorize($user, $model): void
    {
        // The signature of the second argument `$arguments` of `Gate::check`
        // should be [modelClassName, additionalArg, additionalArg...]
        $arguments = $this->getAdditionalArguments();
        array_unshift($arguments, $model);

        $can = $this->gate
            ->forUser($user)
            ->check(
                $this->directiveArgValue('ability'),
                $arguments
            );

        if (! $can) {
            throw new AuthorizationException(
                "You are not authorized to access {$this->definitionNode->name->value}"
            );
        }
    }

    /**
     * Additional arguments that are passed to `Gate::check`.
     *
     * @return mixed[]
     */
    protected function getAdditionalArguments(): array
    {
        return (array) $this->directiveArgValue('args');
    }
}
