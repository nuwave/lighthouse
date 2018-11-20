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
    public function name()
    {
        return 'can';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     * @param \Closure   $next
     *
     * @return FieldValue
     */
    public function handleField(FieldValue $value, \Closure $next)
    {
        $resolver = $value->getResolver();

        return $next(
            $value->setResolver(
                function () use ($resolver) {
                    $user = auth()->user();
                    $gate = resolve(Gate::class);
                    $args = $this->getArguments();

                    $this->getAbilities()->each(function (string $ability) use ($args, $gate, $user) {
                        $this->validate($user, $gate, $ability, $args);
                    });

                    return \call_user_func_array($resolver, \func_get_args());
                }
            )
        );
    }

    /**
     * Get the ability argument.
     *
     * @return Collection
     */
    protected function getAbilities(): Collection
    {
        return collect(
            $this->directiveArgValue('ability') ??
            $this->directiveArgValue('if')
        );
    }

    /**
     * Get the arguments passing to `Gate::check`.
     *
     * @throws \Exception
     *
     * @return array
     */
    protected function getArguments(): array
    {
        $modelClass = $this->getModelClass();
        $args = (array) $this->directiveArgValue('args');

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
     */
    protected function validate($user, Gate $gate, string $ability, array $args)
    {
        $can = $gate->forUser($user)->check($ability, $args);

        if (! $can) {
            throw new AuthorizationException('Not authorized to access this field.');
        }
    }
}
