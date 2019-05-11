<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use Illuminate\Support\Collection;
use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\FieldArgument;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Schema\Factories\NodeFactory;
use Nuwave\Lighthouse\Schema\Conversion\DefinitionNodeConverter;

class SchemaBuilder
{
    /**
     * @var \Nuwave\Lighthouse\Schema\TypeRegistry
     */
    protected $typeRegistry;

    /**
     * @var \Nuwave\Lighthouse\Schema\Factories\NodeFactory
     */
    protected $nodeFactory;

    /**
     * @var \Nuwave\Lighthouse\Schema\TypeRegistry
     */
    protected $definitionNodeConverter;

    /**
     * @param  \Nuwave\Lighthouse\Schema\TypeRegistry  $typeRegistry
     * @param  \Nuwave\Lighthouse\Schema\Factories\NodeFactory  $nodeFactory
     * @param  \Nuwave\Lighthouse\Schema\Conversion\DefinitionNodeConverter  $definitionNodeConverter
     * @return void
     */
    public function __construct(
        TypeRegistry $typeRegistry,
        NodeFactory $nodeFactory,
        DefinitionNodeConverter $definitionNodeConverter
    ) {
        $this->typeRegistry = $typeRegistry;
        $this->nodeFactory = $nodeFactory;
        $this->definitionNodeConverter = $definitionNodeConverter;
    }

    /**
     * Build an executable schema from AST.
     *
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $documentAST
     * @return \GraphQL\Type\Schema
     */
    public function build($documentAST)
    {
        /** @var \GraphQL\Language\AST\TypeDefinitionNode $typeDefinition */
        foreach ($documentAST->typeDefinitions() as $typeDefinition) {
            $type = $this->nodeFactory->handle($typeDefinition);
            $this->typeRegistry->register($type);

            switch ($type->name) {
                case 'Query':
                    /** @var \Nuwave\Lighthouse\Schema\Conversion\DefinitionNodeConverter $queryType */
                    $queryType = $type;
                    continue 2;
                case 'Mutation':
                    /** @var \GraphQL\Type\Definition\ObjectType $mutationType */
                    $mutationType = $type;
                    continue 2;
                case 'Subscription':
                    /** @var \GraphQL\Type\Definition\ObjectType $subscriptionType */
                    $subscriptionType = $type;
                    continue 2;
                default:
                    $types[] = $type;
            }
        }

        if (empty($queryType)) {
            throw new InvariantViolation(
                'The root Query type must be present in the schema.'
            );
        }

        $config = SchemaConfig::create()
            // Always set Query since it is required
            ->setQuery(
                $queryType
            )
            ->setDirectives(
                $this->convertDirectives($documentAST)
                    ->all()
            );

        // Those are optional so only add them if they are present in the schema
        if (isset($mutationType)) {
            $config->setMutation($mutationType);
        }
        if (isset($subscriptionType)) {
            $config->setSubscription($subscriptionType);
        }
        // Not using lazy loading, as we do not have a way of discovering
        // orphaned types at the moment
        if (isset($types)) {
            $config->setTypes($types);
        }

        return new Schema($config);
    }

    /**
     * Set custom client directives.
     *
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $document
     * @return \Illuminate\Support\Collection<\GraphQL\Type\Definition\Directive>
     */
    protected function convertDirectives(DocumentAST $document): Collection
    {
        return $document->directiveDefinitions()
            ->map(function (DirectiveDefinitionNode $directive) {
                return new Directive([
                    'name' => $directive->name->value,
                    'description' => data_get($directive->description, 'value'),
                    'locations' => (new Collection($directive->locations))
                        ->map(function ($location) {
                            return $location->value;
                        })
                        ->all(),
                    'args' => (new Collection($directive->arguments))
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
                        ->all(),
                    'astNode' => $directive,
                ]);
            });
    }
}
