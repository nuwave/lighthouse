<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Type\Definition\Type;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\NodeRegistry;
use Nuwave\Lighthouse\Schema\Values\TypeValue;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\TypeManipulator;
use Nuwave\Lighthouse\Support\Contracts\TypeMiddleware;

class NodeDirective extends BaseDirective implements TypeMiddleware, TypeManipulator, DefinedDirective
{
    /**
     * @var \Nuwave\Lighthouse\Schema\NodeRegistry
     */
    protected $nodeRegistry;

    public function __construct(NodeRegistry $nodeRegistry)
    {
        $this->nodeRegistry = $nodeRegistry;
    }

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Register a type for Relay's global object identification.
When used without any arguments, Lighthouse will attempt
to resolve the type through a model with the same name.
"""
directive @node(
  """
  Reference to resolver function.
  Consists of two parts: a class name and a method name, seperated by an `@` symbol.
  If you pass only a class name, the method name defaults to `__invoke`.
  """
  resolver: String

  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.
  """
  model: String
) on FIELD_DEFINITION
SDL;
    }

    /**
     * Handle type construction.
     */
    public function handleNode(TypeValue $value, Closure $next): Type
    {
        if ($this->directiveHasArgument('resolver')) {
            $resolver = $this->getResolverFromArgument('resolver');
        } else {
            $resolver = function ($id): ?Model {
                /** @var \Illuminate\Database\Eloquent\Model|null $model */
                $model = $this->getModelClass()::find($id);

                return $model;
            };
        }

        $this->nodeRegistry->registerNode(
            $value->getTypeDefinitionName(),
            $resolver
        );

        return $next($value);
    }

    /**
     * Apply manipulations from a type definition node.
     *
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode  $typeDefinition
     */
    public function manipulateTypeDefinition(DocumentAST &$documentAST, TypeDefinitionNode &$typeDefinition): void
    {
        ASTHelper::attachNodeInterfaceToObjectType($typeDefinition);
    }
}
