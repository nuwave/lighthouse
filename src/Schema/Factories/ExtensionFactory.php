<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\TypeExtensionDefinitionNode;
use Nuwave\Lighthouse\Support\Traits\HandlesNodeFields;

class ExtensionFactory
{
    use HandlesNodeFields;

    /**
     * Add extended fields to original type.
     *
     * @param TypeExtensionDefinitionNode $extension
     * @param mixed                       $type
     *
     * @return mixed
     */
    public static function extend(TypeExtensionDefinitionNode $extension, $type)
    {
        $instance = new static();
        $typeFields = $type->config['fields']();
        $fields = $instance->getNodeFields($extension->definition->fields)->toArray();
        $type->config['fields'] = function () use ($typeFields, $fields) {
            return array_merge($typeFields, $fields);
        };

        return $type;
    }

    /**
     * Extract fields from extension node.
     *
     * @param TypeExtensionDefinitionNode $extension
     *
     * @return array
     */
    public static function extractFields(TypeExtensionDefinitionNode $extension)
    {
        $instance = new static();

        return $instance->getNodeFields($extension->definition->fields)->toArray();
    }
}
