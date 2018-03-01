<?php

namespace Nuwave\Lighthouse\Schema\Resolvers;

use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Type\Definition\ObjectType;
use Nuwave\Lighthouse\Support\Traits\HandlesNodeFields;

class ObjectTypeResolver extends AbstractResolver
{
    use HandlesNodeFields;

    /**
     * Instance of enum node to resolve.
     *
     * @var ObjectTypeDefinitionNode
     */
    protected $node;

    /**
     * Generate a GraphQL type from a node.
     *
     * @return ObjectType
     */
    public function generate()
    {
        $config = [
            'name' => $this->getName(),
            'fields' => function () {
                return $this->getFields()->toArray();
            },
        ];

        return new ObjectType($config);
    }

    /**
     * Get name for object type.
     *
     * @return string
     */
    protected function getName()
    {
        return data_get($this->node, 'name.value', '');
    }

    /**
     * Get type fields.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getFields()
    {
        return $this->getNodeFields($this->node);
    }
}
