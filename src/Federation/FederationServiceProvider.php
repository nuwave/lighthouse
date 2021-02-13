<?php

namespace Nuwave\Lighthouse\Federation;

use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Nuwave\Lighthouse\Exceptions\FederationException;
use Nuwave\Lighthouse\Federation\Directives\ExtendsDirective;
use Nuwave\Lighthouse\Federation\Directives\ExternalDirective;
use Nuwave\Lighthouse\Federation\Directives\KeyDirective;
use Nuwave\Lighthouse\Federation\Directives\ProvidesDirective;
use Nuwave\Lighthouse\Federation\Directives\RequiresDirective;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\RootType;

class FederationServiceProvider extends ServiceProvider
{
    public function boot(Dispatcher $dispatcher): void
    {
        $dispatcher->listen(
            ManipulateAST::class,
            function (ManipulateAST &$manipulateAST) {
                $this->addFederationAdjustments($manipulateAST->documentAST);
            }
        );

        $dispatcher->listen(
            RegisterDirectiveNamespaces::class,
            static function (): string {
                return __NAMESPACE__.'\\Directives';
            }
        );
    }

    public function addFederationAdjustments(DocumentAST &$documentAST): void
    {
        $this->addDirectives($documentAST);
        $this->addScalars($documentAST);
        $this->addEntityUnion($documentAST);

        /** @var \GraphQL\Language\AST\ObjectTypeDefinitionNode $queryType */
        $queryType = $documentAST->types['Query'];

        $queryType->fields [] = Parser::fieldDefinition(/** @lang GraphQL */ '
        _entities(
            representations: [_Any!]!
        ): [_Entity]! @field(resolver: "Nuwave\\\Lighthouse\\\Federation\\\Resolvers\\\Entities")
        ');

        $queryType->fields [] = Parser::fieldDefinition(/** @lang GraphQL */ '
        _service: _Service! @field(resolver: "Nuwave\\\Lighthouse\\\Federation\\\Resolvers\\\Service")
        ');

        $documentAST->setTypeDefinition(
            Parser::objectTypeDefinition(/** @lang GraphQL */ '
            type _Service {
                sdl: String
            }
            ')
        );
    }

    /**
     * Add federation specific directives to the AST.
     */
    protected function addDirectives(DocumentAST &$documentAST): void
    {
        $documentAST->setDirectiveDefinition(Parser::directiveDefinition(ExternalDirective::definition()));
        $documentAST->setDirectiveDefinition(Parser::directiveDefinition(RequiresDirective::definition()));
        $documentAST->setDirectiveDefinition(Parser::directiveDefinition(ProvidesDirective::definition()));
        $documentAST->setDirectiveDefinition(Parser::directiveDefinition(KeyDirective::definition()));
        $documentAST->setDirectiveDefinition(Parser::directiveDefinition(ExtendsDirective::definition()));
    }

    /**
     * Add federation specific scalars to the AST.
     */
    protected function addScalars(DocumentAST &$documentAST): void
    {
        $documentAST->setTypeDefinition(
            Parser::scalarTypeDefinition(/** @lang GraphQL */ '
            scalar _Any @scalar(class: "Nuwave\\\Lighthouse\\\Federation\\\Types\\\Any")
            ')
        );

        // TODO check if required or if we could also use `String!` instead of the _FieldSet scalar. Apollo federation demo uses String!
        $documentAST->setTypeDefinition(
            Parser::scalarTypeDefinition(/** @lang GraphQL */ '
            scalar _FieldSet @scalar(class: "Nuwave\\\Lighthouse\\\Federation\\\Types\\\FieldSet")
            ')
        );
    }

    /**
     * Retrieve all object types from AST which uses the @key directive,
     * (no matter if native or extended type) and combine those types into the _Entity union.
     *
     * @throws \Nuwave\Lighthouse\Exceptions\FederationException
     */
    protected function addEntityUnion(DocumentAST &$documentAST): void
    {
        /** @var array<int, string> $entities */
        $entities = [];

        // We just care about object types ... but we don't care about global object types ... and we just want the
        // types which make use of the @key directive
        foreach ($documentAST->types as $type) {
            if (! $type instanceof ObjectTypeDefinitionNode) {
                continue;
            }

            $typeName = $type->name->value;
            if (RootType::isRootType($typeName)) {
                continue;
            }

            /** @var \GraphQL\Language\AST\DirectiveNode $directive */
            foreach ($type->directives as $directive) {
                if ($directive->name->value === 'key') {
                    $entities[] = $typeName;
                    break;
                }
            }
        }

        if (count($entities) === 0) {
            throw new FederationException('There must be at least one type defining the @key directive');
        }

        $entitiesString = implode(' | ', $entities);
        $documentAST->setTypeDefinition(
            Parser::unionTypeDefinition(/** @lang GraphQL */ "
            union _Entity = {$entitiesString}
            ")
        );
    }
}
