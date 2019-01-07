<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Language\AST\Node;
use Nuwave\Lighthouse\Schema\NodeRegistry;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\NodeMiddleware;
use Nuwave\Lighthouse\Support\Contracts\NodeManipulator;

class ModelDirective extends BaseDirective implements NodeMiddleware, NodeManipulator
{
    /**
     * @var \Nuwave\Lighthouse\Schema\NodeRegistry
     */
    protected $nodeRegistry;

    /**
     * @param  NodeRegistry $nodeRegistry
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
        return 'model';
    }

    /**
     * Handle type construction.
     *
     * @param  NodeValue  $value
     * @param  \Closure  $next
     *
     * @return NodeValue
     */
    public function handleNode(NodeValue $value, \Closure $next): NodeValue
    {
        $this->nodeRegistry->registerModel(
            $value->getTypeDefinitionName(), $this->getModelClass('class')
        );

        return $next($value);
    }

    /**
     * @param  Node  $node
     * @param  DocumentAST  $documentAST
     *
     * @return DocumentAST
     */
    public function manipulateSchema(Node $node, DocumentAST $documentAST): DocumentAST
    {
        return ASTHelper::attachNodeInterfaceToObjectType($node, $documentAST);
    }
}
