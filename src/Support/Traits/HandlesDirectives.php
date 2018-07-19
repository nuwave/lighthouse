<?php

namespace Nuwave\Lighthouse\Support\Traits;

use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Support\Utils\DirectiveUtil;

/**
 * Trait HandlesDirectives.
 *
 * @deprecated Use the BaseDirective class or DirectiveUtil instead
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
     * @deprecated this method will be depreciated in the next version in
     * favor of the DirectiveUtil class
     *
     * @return DirectiveNode
     */
    protected function fieldDirective($field, $name)
    {
        return DirectiveUtil::fieldDirective($field, $name);
    }

    /**
     * Get directive argument value.
     *
     * @param DirectiveNode $directive
     * @param string        $name
     * @param mixed         $default
     *
     * @deprecated this method will be depreciated in the next version in
     * favor of the DirectiveUtil class
     *
     * @return mixed
     */
    protected function directiveArgValue(DirectiveNode $directive, $name, $default = null)
    {
        return DirectiveUtil::directiveArgValue($directive, $name, $default);
    }

    /**
     * Get argument's value.
     *
     * @param mixed $arg
     * @param mixed $default
     *
     * @deprecated this method will be depreciated in the next version in
     * favor of the DirectiveUtil class
     *
     * @return mixed
     */
    protected function argValue($arg, $default = null)
    {
        return DirectiveUtil::argValue($arg, $default);
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

    /**
     * Strip description of invalid characters.
     *
     * @param string $description
     *
     * @return string
     */
    protected function safeDescription($description = '')
    {
        return trim(str_replace(["\n", "\t"], '', $description));
    }
}
