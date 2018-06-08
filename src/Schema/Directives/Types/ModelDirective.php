<?php

namespace Nuwave\Lighthouse\Schema\Directives\Types;

use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\AttachesNodeInterface;
use Nuwave\Lighthouse\Schema\Values\TypeValue;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class ModelDirective implements TypeMiddleware, TypeManipulator
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
     * @param TypeValue $value
     *
     * @return TypeValue
     */
    public function handleNode(TypeValue $value)
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
     * @param TypeValue $value
     *
     * @return string
     */
    protected function getModelClassName(TypeValue $value)
    {
        $className = $this->directiveArgValue(
            $this->nodeDirective($value->getNode(), self::name()),
            'class'
        );

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
     * @param ObjectTypeDefinitionNode $objectType
     * @param DocumentAST              $current
     * @param DocumentAST              $original
     *
     * @return DocumentAST
     */
    public function manipulateSchema(ObjectTypeDefinitionNode $objectType, DocumentAST $current, DocumentAST $original)
    {
        return $this->attachNodeInterfaceToObjectType($objectType, $current);
    }
}
