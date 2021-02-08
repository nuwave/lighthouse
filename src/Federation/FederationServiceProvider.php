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
use Nuwave\Lighthouse\Schema\RootType;

class FederationServiceProvider extends ServiceProvider
{
    public function boot(Dispatcher $dispatcher): void
    {
        $dispatcher->listen(
            ManipulateAST::class,
            [$this, 'addFederationAdjustments']
        );

        $dispatcher->listen(
            RegisterDirectiveNamespaces::class,
            static function (): string {
                return __NAMESPACE__ . '\\Directives';
            }
        );
    }

    public function addFederationAdjustments(ManipulateAST $manipulateAST): void
    {
        $this->addDirectives($manipulateAST);
        $this->addScalars($manipulateAST);
        $this->addEntityUnion($manipulateAST);

        /** @var \GraphQL\Language\AST\ObjectTypeDefinitionNode $queryType */
        $queryType = $manipulateAST->documentAST->types['Query'];

        $queryType->fields []= Parser::fieldDefinition(/** @lang GraphQL */ '
        _entities(
            representations: [_Any!]!
        ): [_Entity]! @field(resolver: "Nuwave\\\Lighthouse\\\Federation\\\Resolvers\\\Entity")
        ');

        $queryType->fields []= Parser::fieldDefinition(/** @lang GraphQL */ '
        _service: _Service! @field(resolver: "Nuwave\\\Lighthouse\\\Federation\\\Resolvers\\\Service")
        ');

        $manipulateAST->documentAST->setTypeDefinition(
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
    protected function addDirectives(ManipulateAST &$manipulateAST): void
    {
        $manipulateAST->documentAST->setDirectiveDefinition(Parser::directiveDefinition(ExternalDirective::definition()));
        $manipulateAST->documentAST->setDirectiveDefinition(Parser::directiveDefinition(RequiresDirective::definition()));
        $manipulateAST->documentAST->setDirectiveDefinition(Parser::directiveDefinition(ProvidesDirective::definition()));
        $manipulateAST->documentAST->setDirectiveDefinition(Parser::directiveDefinition(KeyDirective::definition()));
        $manipulateAST->documentAST->setDirectiveDefinition(Parser::directiveDefinition(ExtendsDirective::definition()));
    }

    /**
     * Add federation specific scalars to the AST.
     */
    protected function addScalars(ManipulateAST &$manipulateAST): void
    {
        $manipulateAST->documentAST->setTypeDefinition(
            Parser::scalarTypeDefinition(/** @lang GraphQL */ '
            scalar _Any @scalar(class: "Nuwave\\\Lighthouse\\\Federation\\\Schema\\\Types\\\Scalars\\\Any")
            ')
        );

        // TODO check if required or if we could also use `String!` instead of the _FieldSet scalar. Apollo federation demo uses String!
        $manipulateAST->documentAST->setTypeDefinition(
            Parser::scalarTypeDefinition(/** @lang GraphQL */ '
            scalar _FieldSet @scalar(class: "Nuwave\\\Lighthouse\\\Federation\\\Schema\\\Types\\\Scalars\\\FieldSet")
            ')
        );
    }

    /**
     * Retrieve all object types from AST which uses the @key directive,
     * (no matter if native or extended type) and combine those types into the _Entity union.
     *
     * @throws \Nuwave\Lighthouse\Exceptions\FederationException
     */
    protected function addEntityUnion(ManipulateAST &$manipulateAST): void
    {
        /** @var array<int, string> $entities */
        $entities = [];

        // We just care about object types ... but we don't care about global object types ... and we just want the
        // types which make use of the @key directive
        foreach ($manipulateAST->documentAST->types as $type) {
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
        $manipulateAST->documentAST->setTypeDefinition(
            Parser::unionTypeDefinition(/** @lang GraphQL */ "
            union _Entity = {$entitiesString}
            ")
        );
    }
}
