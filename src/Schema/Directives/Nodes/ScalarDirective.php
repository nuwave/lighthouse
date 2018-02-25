<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\Resolvers\ScalarResolver;

class ScalarDirective extends NodeDirective
{
    /**
     * Resolve the node directive.
     *
     * @param ScalarTypeDefinitionNode $node
     *
     * @return mixed
     */
    public function resolve(Node $node)
    {
        return ScalarResolver::resolve($node);
    }
}
