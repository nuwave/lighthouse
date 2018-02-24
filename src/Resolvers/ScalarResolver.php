<?php

namespace Nuwave\Lighthouse\Resolvers;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Type\Definition\ScalarType;

class ScalarResolver extends AbstractResolver
{
    /**
     * Generate instance of scalar type.
     *
     * @return ScalarType
     */
    public function generate()
    {
        $directive = $this->getDirective($this->node, 'scalar');
        $className = config('lighthouse.namespaces.scalars').'\\'.$this->getClassName($directive);

        return app($className);
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
            ucfirst($this->node->name->value).'ScalarType'
        );
    }
}
