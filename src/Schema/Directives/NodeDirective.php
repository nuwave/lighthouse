<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\NodeRegistry;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use GraphQL\Language\AST\TypeDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\TypeValue;
use Nuwave\Lighthouse\Support\Contracts\TypeMiddleware;
use Nuwave\Lighthouse\Support\Contracts\TypeManipulator;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;

class NodeDirective extends BaseDirective implements TypeMiddleware, TypeManipulator, DefinedDirective
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

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'SDL'
"""
Register a type for relay global object identification.
"""
directive @node(
  """
  Reference to resolver function.
  Consists of two parts: a class name and a method name, seperated by an `@` symbol.
  If you pass only a class name, the method name defaults to `__invoke`.
  """
  resolver: String!
) on FIELD_DEFINITION
SDL;
    }

    /**
     * Handle type construction.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\TypeValue  $value
     * @param  \Closure  $next
     * @return \GraphQL\Type\Definition\Type
     */
    public function handleNode(TypeValue $value, Closure $next): Type
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
    public function manipulateTypeDefinition(DocumentAST &$documentAST, TypeDefinitionNode &$typeDefinition): void
    {
        ASTHelper::attachNodeInterfaceToObjectType($typeDefinition);
    }
}
