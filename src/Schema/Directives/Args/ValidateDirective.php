<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\DirectiveNode;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddleware;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class ValidateDirective implements ArgMiddleware
{
    use HandlesDirectives;

    /**
     * Directive name.
     *
     * @return string
     */
    public function name()
    {
        return 'validate';
    }

    /**
     * Resolve the field directive.
     *
     * @param ArgumentValue $value
     *
     * @return array
     */
    public function handle(ArgumentValue $value)
    {
        // TODO: Rename "getValue" to something more descriptive like "toArray"
        // and consider using for NodeValue/FieldValue.
        $current = $value->getValue();
        $current['rules'] = array_merge(
            array_get($value->getArg(), 'rules', []),
            $this->getRules($value->getDirective())
        );

        return $value->setValue($current);
    }

    /**
     * Get array of rules to apply to field.
     *
     * @param DirectiveNode $directive
     *
     * @return array
     */
    protected function getRules(DirectiveNode $directive)
    {
        return collect($directive->arguments)->map(function (ArgumentNode $arg) {
            return $this->argValue($arg);
        })->collapse()->toArray();
    }
}
