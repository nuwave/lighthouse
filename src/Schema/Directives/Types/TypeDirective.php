<?php

namespace Nuwave\Lighthouse\Schema\Directives\Types;

use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\AttachesNodeInterface;
use Nuwave\Lighthouse\Schema\Values\TypeValue;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class TypeDirective implements TypeMiddleware, TypeManipulator
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
     * @param TypeValue $typeValue
     *
     * @return TypeValue
     */
    public function handleNode(TypeValue $typeValue)
    {
        graphql()->nodes()->registerNode(
            $typeValue->getName(),
            // Resolver for the node itself
            $this->getResolver($typeValue, 'resolver'),
            // Interface type resolver
            $this->getResolver($typeValue, 'typeResolver')
        );

        return $typeValue;
    }

    /**
     * Get node resolver.
     *
     * @param TypeValue $value
     *
     * @return \Closure
     */
    protected function getResolver(TypeValue $value, string $argKey)
    {
        $resolver = $this->directiveArgValue(
            $this->nodeDirective($value->getDefinition(), self::name()),
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
