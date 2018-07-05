<?php

namespace Nuwave\Lighthouse\Schema\Resolvers;

use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\EnumValueDefinitionNode;
use GraphQL\Type\Definition\EnumType;

class EnumResolver extends AbstractResolver
{
    /**
     * Instance of enum node to resolve.
     *
     * @var EnumTypeDefinitionNode
     */
    protected $node;

    /**
     * Generate a new EnumType instance.
     *
     * @return EnumType
     */
    public function generate(): EnumType
    {
        $config = [
            'name' => $this->getName(),
            'values' => $this->getValues(),
        ];

        return new EnumType($config);
    }

    /**
     * Get enum type name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->node->name->value;
    }

    /**
     * Get enum type values.
     *
     * @return array
     */
    public function getValues(): array
    {
        return collect($this->node->values)
            ->mapWithKeys(function (EnumValueDefinitionNode $node) {
                return [$this->getEnumValueKey($node) => $this->parseEnumNode($node)];
            })->toArray();
    }

    /**
     * Get enum value key.
     *
     * @param EnumValueDefinitionNode $node
     *
     * @return string
     */
    protected function getEnumValueKey(EnumValueDefinitionNode $node): string
    {
        return $node->name->value;
    }

    /**
     * Get enum value values.
     *
     * @param EnumValueDefinitionNode $node
     *
     * @return array
     */
    protected function parseEnumNode(EnumValueDefinitionNode $node): array
    {
        $directive = $this->getDirective($node, 'enum');

        if (! $directive) {
            return [];
        }

        return [
            'value' => $this->directiveArgValue($directive, 'value'),
            'description' => $this->safeDescription($node->description),
        ];
    }
}
