<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Language\AST\Node;
use Nuwave\Lighthouse\Schema\NodeRegistry;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use GraphQL\Language\AST\TypeDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Contracts\NodeMiddleware;
use Nuwave\Lighthouse\Support\Contracts\TypeDefinitionManipulator;

class NodeDirective extends BaseDirective implements NodeMiddleware, TypeDefinitionManipulator
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
        return 'node';
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
        $this->nodeRegistry->registerNode(
            $value->getTypeDefinitionName(),
            $this->getResolverFromArgument('resolver')
        );

        return $next($value);
    }

    /**
     * Apply manipulations from a type definition node.
     *
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $documentAST
     * @param  \GraphQL\Language\AST\TypeDefinitionNode  $typeDefinition
     * @return void
     */
    public function manipulateTypeDefinition(DocumentAST &$documentAST, TypeDefinitionNode &$typeDefinition)
    {
        ASTHelper::attachNodeInterfaceToObjectType($typeDefinition);
    }
}
