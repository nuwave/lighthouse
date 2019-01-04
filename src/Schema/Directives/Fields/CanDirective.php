<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Illuminate\Support\Collection;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class CanDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'can';
    }

    /**
     * Ensure the user is authorized to access this field.
     *
     * @param FieldValue $value
     * @param \Closure   $next
     *
     * @return FieldValue
     */
    public function handleField(FieldValue $value, \Closure $next): FieldValue
    {
        $resolver = $value->getResolver();

        return $next(
            $value->setResolver(
                function () use ($resolver) {
                    $user = auth()->user();
                    $gate = app(Gate::class);
                    $gateArguments = $this->getGateArguments();

                    $this->getAbilities()->each(
                        function (string $ability) use ($gate, $user, $gateArguments) {
                            $this->authorize($user, $gate, $ability, $gateArguments);
                        }
                    );

                    return call_user_func_array($resolver, func_get_args());
                }
            )
        );
    }

    /**
     * Get the ability argument.
     *
     * For compatibility reasons, the alias "if" will be kept until the next major version.
     *
     * @return Collection
     */
    protected function getAbilities(): Collection
    {
        return collect(
            $this->directiveArgValue('ability')
            ?? $this->directiveArgValue('if')
        );
    }

    /**
     * Get additional arguments that are passed to `Gate::check`.
     *
     * @return array
     */
    protected function getGateArguments(): array
    {
        $modelClass = $this->getModelClass();
        $args = (array) $this->directiveArgValue('args');

        // The signature of the second argument `$arguments` of `Gate::check`
        // should be [modelClassName, additionalArg, additionalArg...]
        array_unshift($args, $modelClass);

        return $args;
    }

    /**
     * @param Authenticatable|null $user
     * @param Gate                 $gate
     * @param string               $ability
     * @param array                $args
     *
     * @throws AuthorizationException
     *
     * @return void
     */
    protected function authorize($user, Gate $gate, string $ability, array $args)
    {
        $can = $gate->forUser($user)->check($ability, $args);

        if (! $can) {
            throw new AuthorizationException(
                "You are not not authorized to access {$this->definitionNode->name->value}"
            );
        }
    }
}
