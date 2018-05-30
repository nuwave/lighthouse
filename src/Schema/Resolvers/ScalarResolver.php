<?php

namespace Nuwave\Lighthouse\Schema\Resolvers;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Type\Definition\ScalarType;
use Nuwave\Lighthouse\Schema\Directives\Nodes\ScalarDirective;

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
    public function generate()
    {
        $directive = $this->getDirective($this->value->getNode(), ScalarDirective::name());
        $className = $directive ? $this->getClassName($directive) : ucfirst($this->value->getNodeName());
        $namespace = config('lighthouse.namespaces.scalars').'\\'.$className;

        return app($namespace);
    }

    /**
     * Get the class name to instantiate.
     *
     * @param DirectiveNode $directive
     *
     * @return string
     */
    protected function getClassName(DirectiveNode $directive)
    {
        return $this->directiveArgValue(
            $directive,
            'class',
            ucfirst($this->value->getNodeName())
        );
    }
}
