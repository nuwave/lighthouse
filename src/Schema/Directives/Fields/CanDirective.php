<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Closure;
use GraphQL\Error\Error;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class CanDirective implements FieldMiddleware
{
    use HandlesDirectives;

    /**
     * Resolve the field directive.
     *
     * @param FieldDefinitionNode $field
     * @param Closure             $resolver
     *
     * @return Closure
     */
    public function handle(FieldDefinitionNode $field, Closure $resolver)
    {
        $policies = $this->directiveArgValue(
            $this->fieldDirective($field, 'can'),
            'if'
        );

        return function () use ($policies, $resolver) {
            $args = func_get_args();
            $resolved = call_user_func_array($resolver, $args);

            $can = collect($policies)->reduce(function ($allowed, $policy) use ($resolved) {
                if (! auth()->user()->can($policy, $resolved)) {
                    return false;
                }

                return $allowed;
            }, true);

            if (! $can) {
                throw new Error('Not authorized to access resource');
            }

            return $resolved;
        };
    }
}
