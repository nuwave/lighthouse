<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Language\AST\Node;
use Nuwave\Lighthouse\Schema\NodeRegistry;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\NodeMiddleware;
use Nuwave\Lighthouse\Support\Contracts\NodeManipulator;

class NodeDirective extends BaseDirective implements NodeMiddleware, NodeManipulator
{
    /** @var NodeRegistry */
    protected $nodeRegistry;
    
    /**
     * @param NodeRegistry $nodeRegistry
     */
    public function __construct(NodeRegistry $nodeRegistry)
    {
        $this->nodeRegistry = $nodeRegistry;
    }
    
    /**
     * Directive name.
     *
     * @return string
     */
    public function name(): string
    {
        return 'node';
    }

    /**
     * Handle type construction.
     *
     * @param NodeValue $value
     * @param \Closure $next
     *
     * @throws DirectiveException
     * @throws DefinitionException
     *
     * @return NodeValue
     */
    public function handleNode(NodeValue $value, \Closure $next): NodeValue
    {
        $typeName = $value->getNodeName();
        
        $this->nodeRegistry->registerNode(
            $typeName,
            $this->getResolverFromArgument('resolver')
        );

        return $next($value);
    }
    
    /**
     * @param Node $node
     * @param DocumentAST $documentAST
     *
     * @throws \Exception
     *
     * @return DocumentAST
     */
    public function manipulateSchema(Node $node, DocumentAST $documentAST): DocumentAST
    {
        return ASTHelper::attachNodeInterfaceToObjectType($node, $documentAST);
    }
}
