<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Contracts\NodeManipulator;
use Nuwave\Lighthouse\Support\Contracts\NodeMiddleware;
use Nuwave\Lighthouse\Support\Traits\AttachesNodeInterface;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class NodeDirective implements NodeMiddleware, NodeManipulator
{
    use HandlesDirectives, AttachesNodeInterface;

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
     *
     * @return NodeValue
     */
    public function handleNode(NodeValue $value)
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
     * @param string $argKey
     *
     * @return \Closure
     */
    protected function getResolver(NodeValue $value, $argKey)
    {
        $resolver = $this->directiveArgValue(
            $this->nodeDirective($value->getNode(), self::name()),
            $argKey
        );

        list($className, $method) = explode('@', $resolver);

        return function ($id) use ($className, $method) {
            $instance = app($className);

            return call_user_func_array([$instance, $method], [$id]);
        };
    }

    /**
     * @param ObjectTypeDefinitionNode $objectType
     * @param DocumentAST $current
     * @param DocumentAST $original
     *
     * @return DocumentAST
     * @throws \Exception
     */
    public function manipulateSchema(ObjectTypeDefinitionNode $objectType, DocumentAST $current, DocumentAST $original)
    {
        return $this->attachNodeInterfaceToObjectType($objectType, $current);
    }
}
