<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;


use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use GraphQL\Language\AST\Node;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Contracts\NodeManipulator;
use Nuwave\Lighthouse\Support\Contracts\NodeMiddleware;
use Nuwave\Lighthouse\Support\Traits\AttachesNodeInterface;

class ModelDirective extends BaseDirective implements NodeMiddleware, NodeManipulator
{
    use AttachesNodeInterface;

    /**
     * Directive name.
     *
     * @return string
     */
    public function name(): string
    {
        return 'model';
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
        $modelClassName = $this->getModelClassName($value);

        graphql()->nodes()->model(
            $value->getNodeName(), $modelClassName
        );

        return $value;
    }

    /**
     * Get the full class name of the model complete with namespace.
     *
     * @param NodeValue $value
     *
     * @return string
     */
    protected function getModelClassName(NodeValue $value): string
    {
        $className = $this->directiveArgValue('class');

        return $className ?? $this->inferModelClassName($value->getNodeName());
    }

    /**
     * @param string $nodeName
     *
     * @return string
     */
    protected function inferModelClassName(string $nodeName): string
    {
        return config('lighthouse.namespaces.models').'\\'.$nodeName;
    }

    /**
     * @param Node        $node
     * @param DocumentAST $current
     * @param DocumentAST $original
     *
     * @throws \Exception
     *
     * @return DocumentAST
     */
    public function manipulateSchema(Node $node, DocumentAST $current, DocumentAST $original): DocumentAST
    {
        return $this->attachNodeInterfaceToObjectType($node, $current);
    }
}
