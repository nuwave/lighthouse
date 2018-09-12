<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Collection;
use GraphQL\Type\Definition\Directive;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Language\AST\TypeDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Schema\Factories\NodeFactory;
use Nuwave\Lighthouse\Schema\Factories\ValueFactory;
use Nuwave\Lighthouse\Schema\Conversion\DefinitionNodeConverter;

class SchemaBuilder
{
    /** @var TypeRegistry */
    protected $typeRegistry;

    /** @var ValueFactory */
    protected $valueFactory;

    /** @var NodeFactory */
    protected $nodeFactory;
    
    /** @var NodeRegistry */
    protected $nodeRegistry;
    
    /** @var DefinitionNodeConverter */
    protected $definitionNodeConverter;
    /**
     * @param TypeRegistry $typeRegistry
     * @param ValueFactory $valueFactory
     * @param NodeFactory $nodeFactory
     * @param NodeRegistry $nodeRegistry
     * @param DefinitionNodeConverter $definitionNodeConverter
     */
    public function __construct(
        TypeRegistry $typeRegistry,
        ValueFactory $valueFactory,
        NodeFactory $nodeFactory,
        NodeRegistry $nodeRegistry,
        DefinitionNodeConverter $definitionNodeConverter
    ) {
        $this->typeRegistry = $typeRegistry;
        $this->valueFactory = $valueFactory;
        $this->nodeFactory = $nodeFactory;
        $this->nodeRegistry = $nodeRegistry;
        $this->definitionNodeConverter = $definitionNodeConverter;
    }

    /**
     * Build an executable schema from AST.
     *
     * @param DocumentAST $documentAST
     *
     * @return Schema
     */
    public function build($documentAST)
    {
        $usesGlobalNodeInterface = $this->containsTypeThatImplementsNodeInterface($documentAST);
        
        if($usesGlobalNodeInterface){
            $documentAST->unlock();
            $nodeQuery = PartialParser::fieldDefinition(
                'node(id: ID!): Node @field(resolver: "Nuwave\\\Lighthouse\\\Schema\\\NodeRegistry@resolve")'
            );
            $documentAST->addFieldToQueryType($nodeQuery);
        }
        
        $types = $this->convertTypes($documentAST);
        if($usesGlobalNodeInterface){
            $types->push(new InterfaceType([
                'name' => 'Node',
                'description' => 'Interface for types that have a globally unique ID',
                'fields' => [
                    config('lighthouse.global_id_field', '_id') => [
                        'type' => Type::nonNull(Type::id()),
                        'description' => 'Global ID that can be used to resolve any type that implements the Node interface.'
                    ]
                ],
                'resolveType' => function($value) {
                    return $this->nodeRegistry->resolveType($value);
                }
            ]));
        }
        $types->each(function (Type $type) {
            // Register in global type registry
            $this->typeRegistry->register($type);
        });
        
        $this->loadRootOperationFields($types);
        
        $config = SchemaConfig::create()
            // Always set Query since it is required
            ->setQuery(
                $types->firstWhere('name', 'Query')
            )
            ->setDirectives(
                $this->convertDirectives($documentAST)->toArray()
            )
            ->setTypeLoader(function ($name) {
                return $this->typeRegistry->get($name);
            });

        // Those are optional so only add them if they are present in the schema
        if ($mutation = $types->firstWhere('name', 'Mutation')) {
            $config->setMutation($mutation);
        }
        if ($subscription = $types->firstWhere('name', 'Subscription')) {
            $config->setSubscription($subscription);
        }

        return new Schema($config);
    }

    /**
     * The fields for the root operations have to be loaded in advance.
     *
     * This is because they might have to register middleware.
     * Other fields can be lazy-loaded to improve performance.
     *
     * @param Collection $types
     */
    protected function loadRootOperationFields(Collection $types)
    {
        $types->filter($this->isOperationType())
            ->each(function (ObjectType $type) {
                // This resolves the fields which causes the fields MiddlewareDirective to run
                // and thus register the (Laravel)-Middleware for the fields.
                $type->getFields();
            });
    }

    /**
     * Callback to determine whether a type is one of the three root operation types.
     *
     * @return \Closure
     */
    protected function isOperationType(): \Closure
    {
        return function (Type $type) {
            return in_array($type->name, ['Query', 'Mutation', 'Subscription']);
        };
    }

    /**
     * Convert definitions to types.
     *
     * @param DocumentAST $document
     *
     * @return Collection
     */
    public function convertTypes(DocumentAST $document): Collection
    {
        return $document->typeDefinitions()
            ->map(function (TypeDefinitionNode $typeDefinition) {
                $nodeValue = $this->valueFactory->node($typeDefinition);

                return $this->nodeFactory->handle($nodeValue);
            });
    }

    /**
     * Set custom client directives.
     *
     * @param DocumentAST $document
     *
     * @return Collection
     */
    protected function convertDirectives(DocumentAST $document): Collection
    {
        return $document->directiveDefinitions()->map(
            function (DirectiveDefinitionNode $directive) {
                return new Directive([
                    'name' => $directive->name->value,
                    'locations' => collect($directive->locations)->map(function ($location) {
                        return $location->value;
                    })->toArray(),
                    'args' => collect($directive->arguments)->map(function (InputValueDefinitionNode $argument) {
                        return new FieldArgument([
                            'name' => $argument->name->value,
                            'defaultValue' => data_get($argument, 'defaultValue.value', null),
                            'description' => $argument->description,
                            'type' => $this->definitionNodeConverter->toType($argument->type),
                        ]);
                    })->toArray(),
                    'astNode' => $directive,
                ]);
            }
        );
    }
    
    /**
     * Determine if the DocumentAST contains a type that implements the Node interface.
     *
     * @param DocumentAST $documentAST
     *
     * @return bool
     */
    protected function containsTypeThatImplementsNodeInterface(DocumentAST $documentAST): bool
    {
        return $documentAST->objectTypeDefinitions()
            ->contains(function (ObjectTypeDefinitionNode $objectType) {
                return collect($objectType->interfaces)
                    ->contains(function (NamedTypeNode $interface) {
                        return 'Node' === $interface->name->value;
                    });
            });
    }
}
