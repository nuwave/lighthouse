<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema;

use GraphQL\GraphQL;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\ExecutableTypeNodeConverter;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;

class SchemaBuilder
{
    protected Schema $schema;

    public function __construct(
        protected TypeRegistry $typeRegistry,
        protected ASTBuilder $astBuilder,
    ) {}

    public function schema(): Schema
    {
        return $this->schema ??= $this->build(
            $this->astBuilder->documentAST(),
        );
    }

    /** Build an executable schema from an AST. */
    protected function build(DocumentAST $documentAST): Schema
    {
        $config = SchemaConfig::create();

        $this->typeRegistry->setDocumentAST($documentAST);

        // Use lazy type loading to prevent unnecessary work
        $config->setQuery(
            /** @return \GraphQL\Type\Definition\ObjectType */
            fn (): Type => $this->typeRegistry->get(RootType::QUERY),
        );
        $config->setMutation(fn (): ?Type => $this->typeRegistry->search(RootType::MUTATION));
        $config->setSubscription(fn (): ?Type => $this->typeRegistry->search(RootType::SUBSCRIPTION));
        $config->setTypeLoader(fn (string $name): ?Type => $this->typeRegistry->search($name));

        // Enables introspection to list all types in the schema
        $config->setTypes(fn (): array => $this->typeRegistry->possibleTypes());

        // There is no way to resolve directives lazily, so we convert them eagerly
        $directiveFactory = new DirectiveFactory(
            new ExecutableTypeNodeConverter($this->typeRegistry),
        );

        $directives = [];
        foreach ($documentAST->directives as $directiveDefinition) {
            $directives[] = $directiveFactory->handle($directiveDefinition);
        }

        $config->setDirectives(
            array_merge(GraphQL::getStandardDirectives(), $directives),
        );

        return new Schema($config);
    }
}
