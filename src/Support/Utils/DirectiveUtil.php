<?php

namespace Nuwave\Lighthouse\Support\Utils;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\FieldDefinitionNode;

class DirectiveUtil
{
    /**
     * Get directive by name.
     *
     * @param FieldDefinitionNode $field
     * @param string              $name
     *
     * @return DirectiveNode|null
     */
    public static function fieldDirective($field, $name)
    {
        return collect($field->directives)
            ->first(function (DirectiveNode $directive) use ($name) {
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
    public static function directiveArgValue(DirectiveNode $directive, $name, $default = null)
    {
        $arg = collect($directive->arguments)->first(function ($arg) use ($name) {
            return $arg->name->value === $name;
        });

        return $arg ? self::argValue($arg) : $default;
    }

    /**
     * Get argument's value.
     *
     * @param mixed $arg
     * @param mixed $default
     *
     * @return mixed
     */
    public static function argValue($arg, $default = null)
    {
        $value = data_get($arg, 'value');

        if (! $value) {
            return $default;
        }

        if ($value instanceof ListValueNode) {
            return collect($value->values)->map(function ($node) {
                return $node->value;
            })->toArray();
        }

        if ($value instanceof ObjectValueNode) {
            return collect($value->fields)->mapWithKeys(function ($field) {
                return [$field->name->value => self::argValue($field)];
            })->toArray();
        }

        return $value->value;
    }
}
