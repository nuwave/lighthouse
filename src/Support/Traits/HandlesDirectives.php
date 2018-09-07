<?php

namespace Nuwave\Lighthouse\Support\Traits;

use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\FieldDefinitionNode;

/**
 * Trait HandlesDirectives
 * @package Nuwave\Lighthouse\Support\Traits
 * @deprecated Use the BaseDirective class or the ASTHelper instead
 */
trait HandlesDirectives
{
    /**
     * Get directive by name.
     *
     * @param Node   $node
     * @param string $name
     *
     * @return DirectiveNode
     */
    protected function nodeDirective(Node $node, $name)
    {
        return collect($node->directives)->first(function (DirectiveNode $directive) use ($name) {
            return $directive->name->value === $name;
        });
    }

    /**
     * Get directive by name.
     *
     * @param FieldDefinitionNode $field
     * @param string              $name
     *
     * @return DirectiveNode
     */
    protected function fieldDirective($field, $name)
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

        return $arg ? $this->argValue($arg) : $default;
    }

    /**
     * Get argument's value.
     *
     * @param mixed $arg
     * @param mixed $default
     *
     * @return mixed
     */
    protected function argValue($arg, $default = null)
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
                return [$field->name->value => $this->argValue($field)];
            })->toArray();
        }

        return $value->value;
    }

    /**
     * Unpack field definition type.
     *
     * @param Node $node
     *
     * @return string
     */
    protected function unpackNodeToString(Node $node)
    {
        if (in_array($node->kind, ['ListType', 'NonNullType', 'FieldDefinition'])) {
            return $this->unpackNodeToString($node->type);
        }

        return $node->name->value;
    }

    /**
     * Convert node to schema string.
     *
     * @param Node  $node
     * @param array $wrappers
     *
     * @return string
     */
    protected function nodeToString(Node $node, $wrappers = [])
    {
        if ('NonNullType' === $node->kind) {
            return $this->nodeToString($node->type, array_merge($wrappers, ['%s!']));
        } elseif ('ListType' === $node->kind) {
            return $this->nodeToString($node->type, array_merge($wrappers, ['[%s]']));
        }

        return str_replace('%s', '', collect(array_merge($wrappers, [$node->name->value]))
            ->reduce(function ($string, $type) {
                return sprintf($string, $type);
            }, '%s'));
    }
}
