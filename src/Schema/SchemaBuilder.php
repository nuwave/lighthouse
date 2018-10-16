<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use Illuminate\Support\Collection;
use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\FieldArgument;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Schema\Factories\NodeFactory;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
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
     * @param TypeRegistry            $typeRegistry
     * @param ValueFactory            $valueFactory
     * @param NodeFactory             $nodeFactory
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
     * @throws DirectiveException
     * @return Schema
     */
    public function build($documentAST)
    {
        foreach($documentAST->typeDefinitions() as $typeDefinition){
            $type = $this->nodeFactory->handle($typeDefinition);
            $this->typeRegistry->register($type);

            switch($type->name){
                case 'Query':
                    /** @var ObjectType $queryType */
                    $queryType = $type;
                    continue 2;
                case 'Mutation':
                    /** @var ObjectType $mutationType */
                    $mutationType = $type;
                    continue 2;
                case 'Subscription':
                    /** @var ObjectType $subscriptionType */
                    $subscriptionType = $type;
                    continue 2;
                default:
                    $types []= $type;
            }
        }

        if(empty($queryType)){
            throw new InvariantViolation("The root Query type must be present in the schema.");
        }

        $config = SchemaConfig::create()
            // Always set Query since it is required
            ->setQuery(
                $queryType
            )
            // Not using lazy loading, as we do not have a way of discovering
            // orphaned types at the moment
            ->setTypes(
                $types
            )
            ->setDirectives(
                $this->convertDirectives($documentAST)
                    ->toArray()
            );

        // Those are optional so only add them if they are present in the schema
        if (isset($mutationType)) {
            $config->setMutation($mutationType);
        }
        if (isset($subscriptionType)) {
            $config->setSubscription($subscriptionType);
        }

        return new Schema($config);
    }

    /**
     * Set custom client directives.
     *
     * @param DocumentAST $document
     *
     * @return Collection|Directive[]
     */
    protected function convertDirectives(DocumentAST $document): Collection
    {
        return $document->directiveDefinitions()
            ->map(function (DirectiveDefinitionNode $directive) {
                return new Directive([
                    'name' => $directive->name->value,
                    'description' => data_get($directive->description, 'value'),
                    'locations' => collect($directive->locations)
                        ->map(function ($location) {
                            return $location->value;
                        })
                        ->toArray(),
                    'args' => collect($directive->arguments)
                        ->map(function (InputValueDefinitionNode $argument) {
                            $fieldArgumentConfig = [
                                'name' => $argument->name->value,
                                'description' => data_get($argument->description, 'value'),
                                'type' => $this->definitionNodeConverter->toType($argument->type),
                            ];

                            if ($defaultValue = $argument->defaultValue) {
                                $fieldArgumentConfig += [
                                    'defaultValue' => $defaultValue,
                                ];
                            }

                            return new FieldArgument($fieldArgumentConfig);
                        })
                        ->toArray(),
                    'astNode' => $directive,
                ]);
            });
    }
}
