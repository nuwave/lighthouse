<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Exceptions\AuthenticationException;

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
     * Ensure the user is both authenticated and authorized to access this field.
     *
     * @param FieldValue $value
     * @param \Closure $next
     *
     * @return FieldValue
     */
    public function handleField(FieldValue $value, \Closure $next): FieldValue
    {
        $resolver = $value->getResolver();

        return $next(
            $value->setResolver(
                function () use ($resolver) {
                    /** @var Authorizable $user */
                    $user = auth()->user();

                    if (!$user) {
                        throw new AuthenticationException(
                            "You must be authenticated to access {$this->definitionNode->name->value}. Please log in and try again."
                        );
                    }

                    $model = $this->getModelClass();
                    $policies = $this->directiveArgValue('if');

                    collect($policies)->each(
                        function (string $policy) use ($user, $model): void {
                            if (!$user->can($policy, $model)) {
                                throw new AuthorizationException(
                                    "You are not not authorized to access {$this->definitionNode->name->value}"
                                );
                            }
                        }
                    );

                    return call_user_func_array($resolver, func_get_args());
                }
            )
        );
    }
}
