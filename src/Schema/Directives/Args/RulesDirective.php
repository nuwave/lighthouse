<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddleware;

class RulesDirective extends BaseDirective implements ArgMiddleware
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'rules';
    }

    /**
     * Resolve the field directive.
     *
     * @param ArgumentValue $argumentValue
     * @param \Closure $next
     *
     * @return ArgumentValue
     */
    public function handleArgument(ArgumentValue $argumentValue, \Closure $next)
    {
        $argumentValue->rules = array_merge(
            data_get($argumentValue, 'rules', []),
            $this->directiveArgValue('apply', [])
        );
        
        $argumentValue->messages = array_merge(
            data_get($argumentValue, 'messages', []),
            collect($this->directiveArgValue('messages', []))
                ->mapWithKeys(
                    function (string $message, string $path) use ($argumentValue) {
                        return [
                            "{$argumentValue->getAstNode()->name->value}.{$path}" => $message
                        ];
                    }
                )
                ->toArray()
        );

        return $next($argumentValue);
    }
}
