<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Schema\Directives\AttachesNodeInterface;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class ModelDirective implements NodeMiddleware, NodeManipulator
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
        return 'model';
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
        $modelClassName = $this->getModelClassName($value);

        graphql()->nodes()->registerModel(
            $value->getNodeName(), $modelClassName
        );

        return $value;
    }

    /**
     * Get the full classname of the model complete with namespace.
     *
     * @param NodeValue $value
     *
     * @return string
     */
    protected function getModelClassName(NodeValue $value)
    {
        $className = $this->directiveArgValue(
            $this->nodeDirective($value->getNode(), self::name()),
            'class'
        );

        return $className ?? $this->inferModelClassName($value->getNodeName());
    }

    /**
     * @param string $nodeName
     * @return string
     */
    protected function inferModelClassName($nodeName)
    {
        return config('lighthouse.namespaces.models') . '\\' . $nodeName;
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
