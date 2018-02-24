<?php

namespace Nuwave\Lighthouse\Resolvers;

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
    protected $enum;

    /**
     * Create a new instace of enum resolver.
     *
     * @param EnumTypeDefinitionNode $enum
     */
    public function __construct(EnumTypeDefinitionNode $enum)
    {
        $this->enum = $enum;
    }

    /**
     * Resolve enum type from node.
     *
     * @param  EnumTypeDefinitionNode $enum
     * @return EnumType
     */
    public static function resolve(EnumTypeDefinitionNode $enum)
    {
        $instance = new static($enum);

        return $instance->generate();
    }

    /**
     * Generate a new EnumType instance.
     *
     * @return EnumType
     */
    public function generate()
    {
        $config = [
            'name'   => $this->getName(),
            'values' => $this->getValues(),
        ];

        return new EnumType($config);
    }

    /**
     * Get enum type name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->enum->name->value;
    }

    /**
     * Get enum type values.
     *
     * @return array
     */
    public function getValues()
    {
        return collect($this->enum->values)
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
    protected function getEnumValueKey(EnumValueDefinitionNode $node)
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
    protected function parseEnumNode(EnumValueDefinitionNode $node)
    {
        $directive = $this->getDirective($node, "enum");

        if (! $directive) {
            return [];
        }

        return [
            'value'       => $this->directiveArgValue($directive, 'value'),
            'description' => $this->safeDescription($node->description),
        ];
    }
}
