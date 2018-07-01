<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Language\AST\Node;
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
    public function name(): string
    {
        return 'node';
    }

    /**
     * Handle type construction.
     *
     * @param NodeValue $value
     *
     * @return NodeValue
     */
    public function handleNode(NodeValue $value): NodeValue
    {
        graphql()->nodes()->node(
            $value->getNodeName(),
            // Resolver for the node itself
            $this->getResolver($value, 'resolver'),
            // Interface type resolver
            $this->getResolver($value, 'typeResolver')
        );

        return $value;
    }

    /**
     * Get node resolver.
     *
     * @param NodeValue $value
     * @param string    $argKey
     *
     * @return \Closure
     */
    protected function getResolver(NodeValue $value, string $argKey): \Closure
    {
        $resolver = $this->directiveArgValue($argKey);

        if (! $resolver && 'typeResolver' === $argKey) {
            $nodeName = $value->getNodeName();

            return function () use ($nodeName) {
                return graphql()->types()->get($nodeName);
            };
        }

        list($className, $method) = explode('@', $resolver);

        return function ($id) use ($className, $method) {
            $instance = app($className);

            return call_user_func_array([$instance, $method], [$id]);
        };
    }

    /**
     * @param Node $node
     * @param DocumentAST $current
     * @param DocumentAST $original
     *
     * @return DocumentAST
     * @throws \Exception
     */
    public function manipulateSchema(Node $node, DocumentAST $current, DocumentAST $original): DocumentAST
    {
        return $this->attachNodeInterfaceToObjectType($node, $current);
    }
}
