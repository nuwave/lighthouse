<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Language\AST\Node;
use Nuwave\Lighthouse\Schema\NodeRegistry;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\NodeMiddleware;
use Nuwave\Lighthouse\Support\Contracts\NodeManipulator;
use Nuwave\Lighthouse\Support\Traits\AttachesNodeInterface;

class ModelDirective extends BaseDirective implements NodeMiddleware, NodeManipulator
{
    use AttachesNodeInterface;

    /**
     * Directive name.
     *
     * @return string
     */
    public function name()
    {
        return 'model';
    }

    /**
     * Handle type construction.
     *
     * @param NodeValue $value
     * @param \Closure   $next
     *
     * @return NodeValue
     */
    public function handleNode(NodeValue $value, \Closure $next)
    {
        $modelClassName = $this->getModelClassName($value);

        resolve(NodeRegistry::class)->model(
            $value->getNodeName(), $modelClassName
        );

        return $next($value);
    }

    /**
     * Get the full class name of the model complete with namespace.
     *
     * @param NodeValue $value
     *
     * @return string
     */
    protected function getModelClassName(NodeValue $value)
    {
        $className = $this->directiveArgValue('class');

        return $className ?? $this->inferModelClassName($value->getNodeName());
    }

    /**
     * @param string $nodeName
     *
     * @return string
     */
    protected function inferModelClassName($nodeName)
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
    public function manipulateSchema(Node $node, DocumentAST $current, DocumentAST $original)
    {
        return $this->attachNodeInterfaceToObjectType($node, $current);
    }
}
