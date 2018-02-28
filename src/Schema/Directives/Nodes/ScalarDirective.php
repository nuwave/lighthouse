<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\Resolvers\ScalarResolver;

class ScalarDirective extends NodeDirective
{
    /**
     * Name of the directive.
     *
     * @var string
     */
    public function name()
    {
        return 'scalar';
    }

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
