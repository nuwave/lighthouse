<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\Utils\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Schema\Directives\AttachesNodeInterface;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class NodeDirective implements NodeMiddleware, NodeManipulator
{
    use HandlesDirectives;
    use AttachesNodeInterface;

    /**
     * Directive name.
     *
     * @return string
     */
    public static function name()
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
        graphql()->nodes()->registerNode(
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
     *
     * @return \Closure
     */
    protected function getResolver(NodeValue $value, string $argKey)
    {
        $resolver = $this->directiveArgValue(
            $this->nodeDirective($value->getNode(), self::name()),
            $argKey
        );

        list($namespace, $method) = explode('@', $resolver);

        return function ($id) use ($namespace, $method) {
            $instance = app($namespace);

            return call_user_func_array([$instance, $method], [$id]);
        };
    }

    /**
     * @param ObjectTypeDefinitionNode $objectType
     * @param DocumentAST $current
     * @param DocumentAST $original
     *
     * @return DocumentAST
     */
    public function manipulateSchema(ObjectTypeDefinitionNode $objectType, DocumentAST $current, DocumentAST $original)
    {
        return $this->attachNodeInterfaceToObjectType($objectType, $current);
    }
}
