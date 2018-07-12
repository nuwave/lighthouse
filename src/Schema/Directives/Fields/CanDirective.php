<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Closure;
use GraphQL\Error\Error;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
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
     * @param Closure    $next
     *
     * @return FieldValue
     */
    public function handleField(FieldValue $value, Closure $next)
    {
        $policies = $this->directiveArgValue('if');
        $resolver = $value->getResolver();

        return $next($value->setResolver(
            function () use ($policies, $resolver) {
                $args = func_get_args();
                $model = $this->getModelClass() ?: get_class($args[0]);

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
        ));
    }
}
