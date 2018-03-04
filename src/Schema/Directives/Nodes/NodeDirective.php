<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

abstract class NodeDirective
{
    use HandlesDirectives;

    /**
     * Handle construction of type.
     *
     * @param Node $node
     *
     * @return Type
     */
    public function resolve(Node $node)
    {
        $this->handle($this->generateValue($node, get_class($node)));
        // Check if type fields are empty and autoresolve them if so
        return $value->getType();
    }

    /**
     * Handle type construction.
     *
     * @param NodeValue $value
     *
     * @return NodeValue
     */
    abstract public function handle(NodeValue $value);

    /**
     * Generate type definition.
     *
     * @param Node   $node
     * @param string $class
     *
     * @return NodeValue
     */
    protected function generateValue(Node $node, $class)
    {
        dd(trim(str_replace(["\n", "\t"], '', $node->description)));
        dd($node->description);
        $directive = $this->nodeDirective($node, $this->name());
        $value = new NodeValue($node);

        dd([
            'name' => $value->getNode()->name->value,
            'description' => $value->getNode()->description,
            'fields' => [],
        ]);

        return $value->setType(new $class([
            'name' => $value->getNode()->name->value,
            'description' => $value->getNode()->description,
        ]));
    }
}
