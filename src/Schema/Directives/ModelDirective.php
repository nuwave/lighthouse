<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Language\AST\Node;
use Nuwave\Lighthouse\Schema\NodeRegistry;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Contracts\NodeMiddleware;
use Nuwave\Lighthouse\Support\Contracts\NodeManipulator;

class ModelDirective extends BaseDirective implements NodeMiddleware, NodeManipulator
{
    /**
     * @var \Nuwave\Lighthouse\Schema\NodeRegistry
     */
    protected $nodeRegistry;

    /**
     * @param  \Nuwave\Lighthouse\Schema\NodeRegistry  $nodeRegistry
     * @return void
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
     * @param  \Nuwave\Lighthouse\Schema\Values\NodeValue  $value
     * @param  \Closure  $next
     * @return \GraphQL\Type\Definition\Type
     */
    public function handleNode(NodeValue $value, Closure $next)
    {
        $this->nodeRegistry->registerModel(
            $value->getTypeDefinitionName(),
            $this->getModelClass('class')
        );

        return $next($value);
    }

    /**
     * @param  \GraphQL\Language\AST\Node  $node
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $documentAST
     * @return \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    public function manipulateSchema(Node $node, DocumentAST $documentAST): DocumentAST
    {
        return ASTHelper::attachNodeInterfaceToObjectType($node, $documentAST);
    }
}
