<?php

namespace Nuwave\Lighthouse\GlobalId;

use Closure;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\Type;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\TypeValue;
use Nuwave\Lighthouse\Support\Contracts\TypeManipulator;
use Nuwave\Lighthouse\Support\Contracts\TypeMiddleware;

class NodeDirective extends BaseDirective implements TypeMiddleware, TypeManipulator
{
    /**
     * @var \Nuwave\Lighthouse\GlobalId\NodeRegistry
     */
    protected $nodeRegistry;

    public function __construct(NodeRegistry $nodeRegistry)
    {
        $this->nodeRegistry = $nodeRegistry;
    }

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Register a type for Relay's global object identification.

When used without any arguments, Lighthouse will attempt
to resolve the type through a model with the same name.
"""
directive @node(
  """
  Reference to a function that receives the decoded `id` and returns a result.
  Consists of two parts: a class name and a method name, seperated by an `@` symbol.
  If you pass only a class name, the method name defaults to `__invoke`.

  Mutually exclusive with the `model` argument.
  """
  resolver: String

  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.

  Mutually exclusive with the `model` argument.
  """
  model: String
) on OBJECT
GRAPHQL;
    }

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
     * @param  \GraphQL\Language\AST\TypeDefinitionNode&\GraphQL\Language\AST\Node  $typeDefinition
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    public function manipulateTypeDefinition(DocumentAST &$documentAST, TypeDefinitionNode &$typeDefinition): void
    {
        if (! $typeDefinition instanceof ObjectTypeDefinitionNode) {
            throw new DefinitionException(
                "The {$this->name()} directive must only be used on object type definitions, not on {$typeDefinition->kind} {$typeDefinition->name->value}."
            );
        }

        /** @var \GraphQL\Language\AST\NamedTypeNode $namedTypeNode */
        $namedTypeNode = Parser::parseType(GlobalIdServiceProvider::NODE, ['noLocation' => true]);
        $typeDefinition->interfaces[] = $namedTypeNode;

        $globalIdFieldName = config('lighthouse.global_id_field');
        $typeDefinition->fields[] = Parser::fieldDefinition(/** @lang GraphQL */ "{$globalIdFieldName}: ID! @globalId");
    }
}
