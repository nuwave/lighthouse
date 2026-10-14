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

    public function schemaHash(): string
    {
        return $this->astBuilder->documentAST()->hash;
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
        $config->setTypes(
            /** @return array<string, \GraphQL\Type\Definition\Type> */
            fn (): array => $this->typeRegistry->possibleTypes(),
        );

        // Passing scalar overrides explicitly prevents the first lookup of a built-in scalar
        // from discovering them by resolving the lazy types callable, which would eagerly
        // build every type in the schema, see https://github.com/nuwave/lighthouse/issues/2771.
        // TODO remove this check when the minimum version of webonyx/graphql-php includes the method
        if (method_exists($config, 'setScalarOverrides')) { // @phpstan-ignore function.alreadyNarrowedType (backward compatibility with webonyx/graphql-php < 15.31)
            $config->setScalarOverrides($this->typeRegistry->scalarOverrides());
        }

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

        $config->setExtensionASTNodes($documentAST->schemaExtensions);

        return new Schema($config);
    }
}
