<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Language\AST\Node;
use Nuwave\Lighthouse\Schema\NodeRegistry;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\NodeMiddleware;
use Nuwave\Lighthouse\Support\Contracts\NodeManipulator;
use Nuwave\Lighthouse\Support\Traits\AttachesNodeInterface;

class NodeDirective extends BaseDirective implements NodeMiddleware, NodeManipulator
{
    use AttachesNodeInterface;

    /**
     * Directive name.
     *
     * @return string
     */
    public function name()
    {
        return 'node';
    }

    /**
     * Handle type construction.
     *
     * @param NodeValue $value
     * @param \Closure $next
     *
     * @return NodeValue
     */
    public function handleNode(NodeValue $value, \Closure $next)
    {
        resolve(NodeRegistry::class)->node(
            $value->getNodeName(),
            // Resolver for the node itself
            $this->getResolver(),
            // Interface type resolver
            $this->getTypeResolver($value)
        );

        return $next($value);
    }

    /**
     * @param NodeValue $value
     *
     * @return \Closure
     */
    protected function getTypeResolver(NodeValue $value): \Closure
    {
        $nodeName = $value->getNodeName();

        return $this->getResolver(
            function () use ($nodeName) {
                return resolve(TypeRegistry::class)->get($nodeName);
            },
            'typeResolver'
        );
    }

    /**
     * @param Node $node
     * @param DocumentAST $current
     * @param DocumentAST $original
     *
     * @throws \Exception
     *
     * @return DocumentAST
     */
    public function manipulateSchema(Node $node, DocumentAST $current, DocumentAST $original)
    {
        return $this->attachNodeInterfaceToObjectType($node, $current);
    }
}
