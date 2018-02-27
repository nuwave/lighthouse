<?php

namespace Nuwave\Lighthouse\Support\Traits;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ListValueNode;

trait HandlesDirectives
{
    /**
     * Get directive by name.
     *
     * @param FieldDefinitionNode $field
     * @param string              $name
     *
     * @return DirectiveNode
     */
    protected function fieldDirective(FieldDefinitionNode $field, $name)
    {
        return collect($field->directives)->first(function (DirectiveNode $directive) use ($name) {
            return $directive->name->value === $name;
        });
    }

    /**
     * Get directive argument value.
     *
     * @param DirectiveNode $directive
     * @param string        $name
     * @param mixed         $default
     *
     * @return mixed
     */
    protected function directiveArgValue(DirectiveNode $directive, $name, $default = null)
    {
        $arg = collect($directive->arguments)->first(function ($arg) use ($name) {
            return $arg->name->value === $name;
        });

        if (! $arg) {
            return $default;
        }

        $value = $arg->value;

        if ($value instanceof ListValueNode) {
            return collect($value->values)->map(function ($node) {
                return $node->value;
            })->toArray();
        }

        return $value->value;
    }
}
