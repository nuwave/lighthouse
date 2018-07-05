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
    public function name(): string
    {
        return 'rules';
    }

    /**
     * Resolve the field directive.
     *
     * @param ArgumentValue $argumentValue
     *
     * @return ArgumentValue
     */
    public function handleArgument(ArgumentValue $argumentValue): ArgumentValue
    {
        if (in_array($argumentValue->getField()->getNodeName(), ['Query', 'Mutation'])) {
            return $argumentValue;
        }

        return $argumentValue->setRules(
            $this->directiveArgValue('apply', [])
        )->setMessages(
            collect($this->directiveArgValue('messages', []))
                ->mapWithKeys(function (string $message, string $path) use ($argumentValue) {
                    return [$argumentValue->getArgName().".{$path}" => $message];
                })->toArray()
        );
    }
}
