<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\GraphQL;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;

class SchemaBuilder
{
    /**
     * @var \Nuwave\Lighthouse\Schema\TypeRegistry
     */
    protected $typeRegistry;

    /**
     * @var \Nuwave\Lighthouse\Schema\AST\ASTBuilder
     */
    protected $astBuilder;

    /**
     * @var \GraphQL\Type\Schema
     */
    protected $schema;

    public function __construct(TypeRegistry $typeRegistry, ASTBuilder $astBuilder)
    {
        $this->typeRegistry = $typeRegistry;
        $this->astBuilder = $astBuilder;
    }

    public function schema(): Schema
    {
        if (! isset($this->schema)) {
            return $this->schema = $this->build(
                $this->astBuilder->documentAST()
            );
        }

        return $this->schema;
    }

    /**
     * Build an executable schema from an AST.
     */
    protected function build(DocumentAST $documentAST): Schema
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
            $config->setSubscription($subscription);
        }

        // Use lazy type loading to prevent unnecessary work
        $config->setTypeLoader(
            function (string $name): Type {
                return $this->typeRegistry->get($name);
            }
        );

        // Enables introspection to list all types in the schema
        $config->setTypes(
            /**
             * @return array<string, \GraphQL\Type\Definition\Type>
             */
            function (): array {
                return $this->typeRegistry->possibleTypes();
            }
        );

        // There is no way to resolve directives lazily, so we convert them eagerly
        $directiveFactory = new DirectiveFactory(
            new ExecutableTypeNodeConverter($this->typeRegistry)
        );

        $directives = [];
        foreach ($documentAST->directives as $directiveDefinition) {
            $directives[] = $directiveFactory->handle($directiveDefinition);
        }

        $config->setDirectives(
            array_merge(GraphQL::getStandardDirectives(), $directives)
        );

        return new Schema($config);
    }
}
