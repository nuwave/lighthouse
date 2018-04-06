<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

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
     * @return FieldValue
     */
    public function handleField(FieldValue $value)
    {
        $policies = $this->directiveArgValue(
            $this->fieldDirective($value->getField(), 'can'),
            'if'
        );

        $resolver = $value->getResolver();

        return $value->setResolver(
            function () use ($policies, $resolver) {
                $args = func_get_args();
                $root = $args[0];

                $can = collect($policies)->reduce(function ($allowed, $policy) use ($root) {
                    if (! auth()->user()->can($policy, get_class($root))) {
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
    }
}
