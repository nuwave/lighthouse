<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use Nuwave\Lighthouse\ClientDirectives\ClientDirectiveFactory;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;

class SchemaBuilder
{
    /**
     * @var \Nuwave\Lighthouse\Schema\TypeRegistry
     */
    protected $typeRegistry;

    /**
     * @var \Nuwave\Lighthouse\ClientDirectives\ClientDirectiveFactory
     */
    protected $clientDirectiveFactory;

    public function __construct(
        TypeRegistry $typeRegistry,
        ClientDirectiveFactory $clientDirectiveFactory
    ) {
        $this->typeRegistry = $typeRegistry;
        $this->clientDirectiveFactory = $clientDirectiveFactory;
    }

    /**
     * Build an executable schema from AST.
     */
    public function build(DocumentAST $documentAST): Schema
    {
        $config = SchemaConfig::create();

        $this->typeRegistry->setDocumentAST($documentAST);

        // Always set Query since it is required
        /** @var \GraphQL\Type\Definition\ObjectType $query */
        $query = $this->typeRegistry->get(RootType::QUERY);
        $config->setQuery($query);

        // Mutation and Subscription are optional, so only add them
        // if they are present in the schema
        if (isset($documentAST->types[RootType::MUTATION])) {
            /** @var \GraphQL\Type\Definition\ObjectType $mutation */
            $mutation = $this->typeRegistry->get(RootType::MUTATION);
            $config->setMutation($mutation);
        }

        if (isset($documentAST->types[RootType::SUBSCRIPTION])) {
            /** @var \GraphQL\Type\Definition\ObjectType $subscription */
            $subscription = $this->typeRegistry->get(RootType::SUBSCRIPTION);
            // Eager-load the subscription fields to ensure they are registered
            $subscription->getFields();

            $config->setSubscription($subscription);
        }

        // Use lazy type loading to prevent unnecessary work
        $config->setTypeLoader(
            [$this->typeRegistry, 'get']
        );

        // This is just used for introspection, it is required
        // to be able to retrieve all the types in the schema
        $config->setTypes(
            [$this->typeRegistry, 'possibleTypes']
        );

        // There is no way to resolve client directives lazily,
        // so we convert them eagerly
        $clientDirectives = [];
        foreach ($documentAST->directives as $directiveDefinition) {
            $clientDirectives [] = $this->clientDirectiveFactory->handle($directiveDefinition);
        }
        $config->setDirectives(
            array_merge(GraphQL::getStandardDirectives(), $clientDirectives)
        );

        return new Schema($config);
    }
}
