<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\GlobalId;

use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\Parser;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Schema\Values\TypeValue;
use Nuwave\Lighthouse\Support\Contracts\TypeManipulator;
use Nuwave\Lighthouse\Support\Contracts\TypeMiddleware;

class NodeDirective extends BaseDirective implements TypeMiddleware, TypeManipulator
{
    public const NODE_INTERFACE_NAME = 'Node';

    public function __construct(
        protected NodeRegistry $nodeRegistry,
        protected ConfigRepository $config,
    ) {}

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

  Mutually exclusive with `model`.
  """
  resolver: String

  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.

  Mutually exclusive with `resolver`.
  """
  model: String
) on OBJECT
GRAPHQL;
    }

    public function handleNode(TypeValue $value): void
    {
        if ($this->directiveHasArgument('resolver')) {
            $resolver = $this->getResolverFromArgument('resolver');
        } else {
            $modelClass = $this->getModelClass();
            $resolver = static fn (int|string $id): ?Model => $modelClass::find($id);
        }

        $this->nodeRegistry->registerNode(
            $value->getTypeDefinitionName(),
            $resolver,
        );
    }

    /** @param  \GraphQL\Language\AST\TypeDefinitionNode&\GraphQL\Language\AST\Node  $typeDefinition */
    public function manipulateTypeDefinition(DocumentAST &$documentAST, TypeDefinitionNode &$typeDefinition): void
    {
        $this->validateMutuallyExclusiveArguments(['model', 'resolver']);

        if (! $typeDefinition instanceof ObjectTypeDefinitionNode) {
            throw new DefinitionException("The {$this->name()} directive must only be used on object type definitions, not on {$typeDefinition->kind} {$typeDefinition->getName()->value}.");
        }

        $namedTypeNode = Parser::parseType(self::NODE_INTERFACE_NAME, ['noLocation' => true]);
        assert($namedTypeNode instanceof NamedTypeNode);
        $typeDefinition->interfaces[] = $namedTypeNode;

        $globalIdFieldName = $this->config->get('lighthouse.global_id_field');
        $typeDefinition->fields[] = Parser::fieldDefinition(/** @lang GraphQL */ "{$globalIdFieldName}: ID! @globalId");

        if (! isset($documentAST->types[self::NODE_INTERFACE_NAME])) {
            $nodeInterfaceName = self::NODE_INTERFACE_NAME;
            $nodeRegistryClass = addslashes(NodeRegistry::class);

            $documentAST->setTypeDefinition(
                Parser::interfaceTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
                "Any object implementing this type can be found by ID through `Query.node`."
                interface {$nodeInterfaceName} @interface(resolveType: "{$nodeRegistryClass}@resolveType") {
                  "Global identifier that can be used to resolve any Node implementation."
                  {$globalIdFieldName}: ID!
                }
                GRAPHQL
                ),
            );

            $queryType = $documentAST->types[RootType::QUERY];
            assert($queryType instanceof ObjectTypeDefinitionNode);

            if (! ASTHelper::hasNode($queryType->fields, 'node')) {
                $queryType->fields[] = Parser::fieldDefinition(/** @lang GraphQL */ <<<GRAPHQL
                  node(id: ID! @globalId): Node @field(resolver: "{$nodeRegistryClass}@resolve")
                GRAPHQL
                );
            }
        }
    }
}
