<?php

namespace Nuwave\Lighthouse\Federation;

use GraphQL\Language\AST\ObjectTypeDefinitionNode;
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
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\PartialParser;

class FederationServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(Dispatcher $dispatcher): void
    {
        $dispatcher->listen(
            ManipulateAST::class,
            [$this, 'addFederationAdjustments']
        );

        $dispatcher->listen(
            RegisterDirectiveNamespaces::class,
            function (RegisterDirectiveNamespaces $registerDirectiveNamespaces): string {
                return sprintf('%s%s', __NAMESPACE__, '\\Directives');
            }
        );
    }

    public function addFederationAdjustments(ManipulateAST $manipulateAST): void
    {
        if (config('lighthouse.federation.type') === 'service') {
            $this->addDirectives($manipulateAST);
            $this->addScalars($manipulateAST);
            $this->addEntityUnion($manipulateAST);

            /** @var \GraphQL\Language\AST\ObjectTypeDefinitionNode $queryType */
            $queryType = $manipulateAST->documentAST->types['Query'];

            $queryType->fields = ASTHelper::mergeNodeList(
                $queryType->fields,
                [
                    PartialParser::fieldDefinition('_entities(representations: [_Any!]!): [_Entity]! @field(resolver: "Nuwave\\\Lighthouse\\\Federation\\\Resolvers\\\Entity@resolve")'),
                    PartialParser::fieldDefinition('_service: _Service! @field(resolver: "Nuwave\\\Lighthouse\\\Federation\\\Resolvers\\\Service@resolveSdl")'),
                ]
            );

            $manipulateAST->documentAST->setTypeDefinition(
                PartialParser::objectTypeDefinition('
                type _Service {
                    sdl: String
                }
                ')
            );
        }

        // TODO add gateway support
    }

    /**
     * Add federation specific directives to the AST.
     */
    protected function addDirectives(ManipulateAST &$manipulateAST): void
    {
        $manipulateAST->documentAST->setDirectiveDefinition(PartialParser::directiveDefinition(ExternalDirective::definition()));
        $manipulateAST->documentAST->setDirectiveDefinition(PartialParser::directiveDefinition(RequiresDirective::definition()));
        $manipulateAST->documentAST->setDirectiveDefinition(PartialParser::directiveDefinition(ProvidesDirective::definition()));
        $manipulateAST->documentAST->setDirectiveDefinition(PartialParser::directiveDefinition(KeyDirective::definition()));
        $manipulateAST->documentAST->setDirectiveDefinition(PartialParser::directiveDefinition(ExtendsDirective::definition()));
    }

    /**
     * Add federation specific scalars to the AST.
     */
    protected function addScalars(ManipulateAST &$manipulateAST): void
    {
        $manipulateAST->documentAST->setTypeDefinition(
            PartialParser::scalarTypeDefinition(
                'scalar _Any @scalar(class: "Nuwave\\\Lighthouse\\\Federation\\\Schema\\\Types\\\Scalars\\\Any")'
            )
        );

        // TODO check if required or if we could also use `String!` instead of the _FieldSet scalar. Apollo federation demo uses String!
        $manipulateAST->documentAST->setTypeDefinition(
            PartialParser::scalarTypeDefinition(
                'scalar _FieldSet @scalar(class: "Nuwave\\\Lighthouse\\\Federation\\\Schema\\\Types\\\Scalars\\\FieldSet")'
            )
        );
    }

    /**
     * Retrieve all object types from AST which uses the @key directive (no matter if native or extended type) and
     * combine those types into the _Entity union.
     *
     *
     * @throws FederationException
     */
    protected function addEntityUnion(ManipulateAST &$manipulateAST): void
    {
        $entities = [];

        // We just care about object types ... but we don't care about global object types ... and we just want the
        // types which make use of the @key directive
        foreach ($manipulateAST->documentAST->types as $type) {
            if (! ($type instanceof ObjectTypeDefinitionNode)
                || in_array($type->name->value, ['Query', 'Mutation', 'Subscription'])
                || (count($type->directives) === 0)) {
                continue;
            }

            /** @var \GraphQL\Language\AST\DirectiveNode $directive */
            foreach ($type->directives as $directive) {
                if ($directive->name->value === 'key') {
                    $entities[] = $type->name->value;
                    break;
                }
            }
        }

        if (count($entities) === 0) {
            throw new FederationException('There must be at least one type defining the @key directive');
        }

        $manipulateAST->documentAST->setTypeDefinition(
            PartialParser::unionTypeDefinition(sprintf('union _Entity = %s', implode(' | ', $entities)))
        );
    }
}
