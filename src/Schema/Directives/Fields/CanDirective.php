<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Error\Error;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class CanDirective extends BaseFieldDirective implements FieldMiddleware
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
     *
     * @return FieldValue
     */
    public function handleField(FieldValue $value)
    {
        $policies = $this->associatedArgValue('if');
        $model = $this->associatedArgValue('model');
        $resolver = $value->getResolver();

        return $value->setResolver(
            function () use ($policies, $resolver, $model) {
                $args = func_get_args();
                $model = $model ?: get_class($args[0]);

                $can = collect($policies)->reduce(function ($allowed, $policy) use ($model) {
                    if (!app('auth')->user()->can($policy, $model)) {
                        return false;
                    }

                    return $allowed;
                }, true);

                if (!$can) {
                    throw new Error('Not authorized to access resource');
                }

                return call_user_func_array($resolver, $args);
            }
        );
    }
}
