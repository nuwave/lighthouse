<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Language\AST\EnumValueDefinitionNode;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Contracts\NodeResolver;

class EnumDirective extends BaseDirective implements NodeResolver
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'enum';
    }

    /**
     * Resolve the NodeValue to a GraphQL Type.
     *
     * @param NodeValue $value
     *
     * @return Type
     */
    public function resolveNode(NodeValue $value): Type
    {
        return new EnumType([
            'name' => $value->getNodeName(),
            'values' => collect($value->getNode()->values)
                ->mapWithKeys(function (EnumValueDefinitionNode $field) {
                    $directive = $this->directiveDefinition( 'enum', $field);

                    if (!$directive) {
                        return [];
                    }

                    return [$field->name->value => [
                        'value' => $this->directiveArgValue('value', null, $directive),
                        'description' => $this->safeDescription($field->description),
                    ]];
                })->toArray(),
        ]);
    }

    /**
     * Strip description of invalid characters.
     *
     * @param string $description
     *
     * @return string
     */
    protected function safeDescription(string $description = ''): string
    {
        return trim(str_replace(["\n", "\t"], '', $description));
    }
}
