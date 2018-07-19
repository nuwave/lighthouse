<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Type\Definition\EnumType;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use GraphQL\Language\AST\EnumValueDefinitionNode;
use Nuwave\Lighthouse\Support\Contracts\NodeResolver;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class EnumDirective extends BaseDirective implements NodeResolver
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'enum';
    }

    /**
     * Resolve the NodeValue to a GraphQL Type.
     *
     * @param NodeValue $value
     *
     * @return \GraphQL\Type\Definition\Type
     */
    public function resolveNode(NodeValue $value)
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
                        'description' => $field->description
                    ]];
                })->toArray(),
        ]);
    }
}
