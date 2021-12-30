<?php

namespace Nuwave\Lighthouse\Federation;

use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Exceptions\FederationException;
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
        $documentAST->setTypeDefinition(
            Parser::scalarTypeDefinition(/** @lang GraphQL */ '
            scalar _Any @scalar(class: "Nuwave\\\Lighthouse\\\Federation\\\Types\\\Any")
            ')
        );

        $documentAST->setTypeDefinition(
            Parser::scalarTypeDefinition(/** @lang GraphQL */ '
            scalar _FieldSet @scalar(class: "Nuwave\\\Lighthouse\\\Federation\\\Types\\\FieldSet")
            ')
        );
    }

    /**
     * Combine object types with @key into the _Entity union.
     *
     * @throws \Nuwave\Lighthouse\Exceptions\FederationException
     */
    protected function addEntityUnion(DocumentAST &$documentAST): void
    {
        /** @var array<int, string> $entities */
        $entities = [];

        foreach ($documentAST->types as $type) {
            if (! $type instanceof ObjectTypeDefinitionNode) {
                continue;
            }

            /** @var \GraphQL\Language\AST\DirectiveNode $directive */
            foreach ($type->directives as $directive) {
                if ('key' === $directive->name->value) {
                    $entities[] = $type->name->value;
                    break;
                }
            }
        }

        if (0 === count($entities)) {
            throw new FederationException('There must be at least one type using the @key directive when federation is enabled.');
        }

        $entitiesString = implode(' | ', $entities);
        $documentAST->setTypeDefinition(
            Parser::unionTypeDefinition(/** @lang GraphQL */ "
            union _Entity = {$entitiesString}
            ")
        );
    }

    protected function addRootFields(DocumentAST &$documentAST): void
    {
        // In federation it is fine for a schema to not have a user-defined root query type,
        // since we add two federation related fields to it here.
        if (! isset($documentAST->types[RootType::QUERY])) {
            $documentAST->types[RootType::QUERY] = Parser::objectTypeDefinition(/** @lang GraphQL */ 'type Query');
        }

        /** @var \GraphQL\Language\AST\ObjectTypeDefinitionNode $queryType */
        $queryType = $documentAST->types[RootType::QUERY];

        $queryType->fields[] = Parser::fieldDefinition(/** @lang GraphQL */ '
        _entities(
            representations: [_Any!]!
        ): [_Entity]! @field(resolver: "Nuwave\\\Lighthouse\\\Federation\\\Resolvers\\\Entities")
        ');

        $queryType->fields[] = Parser::fieldDefinition(/** @lang GraphQL */ '
        _service: _Service! @field(resolver: "Nuwave\\\Lighthouse\\\Federation\\\Resolvers\\\Service")
        ');
    }

    protected function addServiceType(DocumentAST &$documentAST): void
    {
        $documentAST->setTypeDefinition(
            Parser::objectTypeDefinition(/** @lang GraphQL */ '
            type _Service {
                sdl: String
            }
            ')
        );
    }
}
