<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Federation;

use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Federation\Resolvers\Entities;
use Nuwave\Lighthouse\Federation\Resolvers\Service;
use Nuwave\Lighthouse\Federation\Types\Any;
use Nuwave\Lighthouse\Federation\Types\FieldSet;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\RootType;

class ASTManipulator
{
    public function handle(ManipulateAST $manipulateAST): void
    {
        $documentAST = $manipulateAST->documentAST;

        $this->addScalars($documentAST);
        $this->addEntityUnion($documentAST);
        $this->addRootFields($documentAST);
        $this->addServiceType($documentAST);
    }

    protected function addScalars(DocumentAST &$documentAST): void
    {
        $anyClass = addslashes(Any::class);
        $documentAST->setTypeDefinition(Parser::scalarTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
            scalar _Any @scalar(class: "{$anyClass}")
        GRAPHQL));

        $fieldSetClass = addslashes(FieldSet::class);
        $documentAST->setTypeDefinition(Parser::scalarTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
            scalar _FieldSet @scalar(class: "{$fieldSetClass}")
        GRAPHQL));
    }

    /** Combine object types with @key into the _Entity union. */
    protected function addEntityUnion(DocumentAST &$documentAST): void
    {
        /** @var array<int, string> $entities */
        $entities = [];

        foreach ($documentAST->types as $type) {
            if (! $type instanceof ObjectTypeDefinitionNode) {
                continue;
            }

            foreach ($type->directives as $directive) {
                if ($directive->name->value === 'key') {
                    $entities[] = $type->name->value;
                    break;
                }
            }
        }

        if ($entities === []) {
            throw new FederationException('There must be at least one type using the @key directive when federation is enabled.');
        }

        $entitiesString = implode(' | ', $entities);
        $documentAST->setTypeDefinition(
            Parser::unionTypeDefinition(/** @lang GraphQL */ "
            union _Entity = {$entitiesString}
            "),
        );
    }

    protected function addRootFields(DocumentAST &$documentAST): void
    {
        // In federation, it is fine for a schema to not have a user-defined root query type,
        // since we add two federation related fields to it here.
        $documentAST->types[RootType::QUERY] ??= Parser::objectTypeDefinition(/** @lang GraphQL */ 'type Query');

        $queryType = $documentAST->types[RootType::QUERY];
        assert($queryType instanceof ObjectTypeDefinitionNode);

        $entitiesClass = addslashes(Entities::class);
        $queryType->fields[] = Parser::fieldDefinition(/** @lang GraphQL */ <<<GRAPHQL
            _entities(
                representations: [_Any!]!
            ): [_Entity]! @field(resolver: "{$entitiesClass}")
        GRAPHQL);

        $serviceClass = addslashes(Service::class);
        $queryType->fields[] = Parser::fieldDefinition(/** @lang GraphQL */ <<<GRAPHQL
           _service: _Service! @field(resolver: "{$serviceClass}")
        GRAPHQL);
    }

    protected function addServiceType(DocumentAST &$documentAST): void
    {
        $documentAST->setTypeDefinition(
            Parser::objectTypeDefinition(/** @lang GraphQL */ '
            type _Service {
                sdl: String
            }
            '),
        );
    }
}
