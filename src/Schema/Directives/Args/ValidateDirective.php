<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ListValueNode;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddleware;

class ValidateDirective implements ArgMiddleware
{
    /**
     * Directive name.
     *
     * @return string
     */
    public static function name()
    {
        return 'validate';
    }

    /**
     * Resolve the field directive.
     *
     * @param InputValueDefinitionNode $arg
     * @param DirectiveNode            $directive
     * @param array                    $value
     *
     * @return array
     */
    public function handle(InputValueDefinitionNode $arg, DirectiveNode $directive, array $value)
    {
        $value['rules'] = array_merge(
            array_get($arg, 'rules', []),
            $this->getRules($directive)
        );

        return $value;
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
            return $arg->value;
        })->filter(function ($value) {
            return $value instanceof ListValueNode;
        })->map(function (ListValueNode $list) {
            return collect($list->values)->map(function ($node) {
                return $node->value;
            })->toArray();
        })->collapse()->toArray();
    }
}
