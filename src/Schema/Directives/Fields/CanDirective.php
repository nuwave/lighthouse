<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Closure;
use GraphQL\Error\Error;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class CanDirective implements FieldMiddleware
{
    use HandlesDirectives;

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
     *
     * @param Closure $next
     * @return FieldValue
     */
    public function handleField(FieldValue $value, Closure $next)
    {
        $policies = $this->directiveArgValue(
            $this->fieldDirective($value->getField(), 'can'),
            'if'
        );

        $model = $this->directiveArgValue(
            $this->fieldDirective($value->getField(), 'can'),
            'model'
        );

        $resolver = $value->getResolver();

        $value->setResolver(
            function () use ($policies, $resolver, $model) {
                $args = func_get_args();
                $model = $model ?: get_class($args[0]);

                $can = collect($policies)->reduce(function ($allowed, $policy) use ($model) {
                    if (! app('auth')->user()->can($policy, $model)) {
                        return false;
                    }

                    return $allowed;
                }, true);

                if (! $can) {
                    throw new Error('Not authorized to access resource');
                }

                return call_user_func_array($resolver, $args);
            }
        );

        return $next($value);
    }
}
