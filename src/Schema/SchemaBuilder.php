<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Collection;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Language\AST\TypeDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use GraphQL\Language\AST\DirectiveDefinitionNode;
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
    /** @var DefinitionNodeConverter */
    protected $definitionNodeConverter;

    /**
     * @param TypeRegistry $typeRegistry
     * @param ValueFactory $valueFactory
     * @param NodeFactory $nodeFactory
     * @param DefinitionNodeConverter $definitionNodeConverter
     */
    public function __construct(
        TypeRegistry $typeRegistry,
        ValueFactory $valueFactory,
        NodeFactory $nodeFactory,
        DefinitionNodeConverter $definitionNodeConverter
    ) {
        $this->typeRegistry = $typeRegistry;
        $this->valueFactory = $valueFactory;
        $this->nodeFactory = $nodeFactory;
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
        $types = $this->convertTypes($documentAST);
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
            ->setTypeLoader(
                [$this->typeRegistry, 'get']
            );

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
                return $this->nodeFactory->handle($typeDefinition);
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
                    'description' => data_get($directive->description, 'value'),
                    'locations' => collect($directive->locations)->map(function ($location) {
                        return $location->value;
                    })->toArray(),
                    'args' => collect($directive->arguments)->map(function (InputValueDefinitionNode $argument) {
                        return new FieldArgument([
                            'name' => $argument->name->value,
                            'defaultValue' => data_get($argument->defaultValue, 'value'),
                            'description' => data_get($argument->description, 'value'),
                            'type' => $this->definitionNodeConverter->toType($argument->type),
                        ]);
                    })->toArray(),
                    'astNode' => $directive,
                ]);
            }
        );
    }
}
