<?php

namespace Nuwave\Lighthouse\Schema\Resolvers;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;

class ScalarResolver extends AbstractResolver
{
    /**
     * Scalar node type.
     *
     * @var ScalarTypeDefinitionNode
     */
    protected $node;

    /**
     * Generate instance of scalar type.
     *
     * @return ScalarType
     */
    public function generate(): ScalarType
    {
        $directive = $this->getDirective($this->value->getNode(), 'scalar');
        $className = $directive
            ? $this->getClassName($directive)
            : ucfirst($this->value->getNodeName());
        $namespace = config('lighthouse.namespaces.scalars').'\\'.$className;

        if (! class_exists($namespace) && class_exists($className)) {
            $namespace = $className;
        } elseif (! class_exists($namespace) && ! class_exists($className)) {
            $message = sprintf(
                'Unable to find class [%s] assigned to %s scalar',
                $className,
                $this->value->getNodeName()
            );

            throw new DirectiveException($message);
        }

        return app($namespace);
    }

    /**
     * Get the class name to instantiate.
     *
     * @param DirectiveNode $directive
     *
     * @return string
     */
    protected function getClassName(DirectiveNode $directive): string
    {
        return $this->directiveArgValue(
            $directive,
            'class',
            ucfirst($this->value->getNodeName())
        );
    }
}
