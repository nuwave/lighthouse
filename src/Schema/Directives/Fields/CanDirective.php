<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Nuwave\Lighthouse\Exceptions\AuthenticationException;

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
     * @param \Closure $next
     *
     * @return FieldValue
     */
    public function handleField(FieldValue $value, \Closure $next)
    {
        $resolver = $value->getResolver();

        return $next(
            $value->setResolver(
                function () use ($resolver) {
                    /** @var Authorizable $user */
                    $user = auth()->user();

                    if (!$user) {
                        throw new AuthenticationException('Not authenticated to access this field.');
                    }

                    $model = $this->getModelClass();
                    $policies = $this->directiveArgValue('if');

                    collect($policies)->each(function (string $policy) use ($user, $model) {
                        if (!$user->can($policy, $model)) {
                            throw new AuthorizationException('Not authorized to access this field.');
                        }
                    });

                    return call_user_func_array($resolver, func_get_args());
                }
            )
        );
    }
}
