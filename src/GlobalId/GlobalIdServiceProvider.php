<?php

namespace Nuwave\Lighthouse\GlobalId;

use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Support\Contracts\GlobalId as GlobalIdContract;

class GlobalIdServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(GlobalIdContract::class, GlobalId::class);
        $this->app->singleton(NodeRegistry::class);
    }

    public function boot(Dispatcher $dispatcher): void
    {
        $dispatcher->listen(
            ManipulateAST::class,
            function (ManipulateAST $manipulateAST): void {
                $documentAST = $manipulateAST->documentAST;
                $this->addNodeSupport($documentAST);
            }
        );

        $dispatcher->listen(
            RegisterDirectiveNamespaces::class,
            function (RegisterDirectiveNamespaces $registerDirectiveNamespaces): string {
                return __NAMESPACE__;
            }
        );
    }

    /**
     * Inject the Node interface and a node field into the Query type.
     */
    protected function addNodeSupport(DocumentAST $documentAST): void
    {
        // Only add the node type and node field if a type actually implements them
        // Otherwise, a validation error is thrown
        if (! $this->hasTypeImplementingInterface($documentAST, 'Node')) {
            return;
        }

        $globalId = config('lighthouse.global_id_field');
        // Double slashes to escape the slashes in the namespace.
        $documentAST->setTypeDefinition(
            Parser::interfaceTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
"Node global interface"
interface Node @interface(resolveType: "Nuwave\\\Lighthouse\\\GlobalId\\\NodeRegistry@resolveType") {
"Global identifier that can be used to resolve any Node implementation."
$globalId: ID!
}
GRAPHQL
            )
        );

        /** @var \GraphQL\Language\AST\ObjectTypeDefinitionNode $queryType */
        $queryType = $documentAST->types[RootType::QUERY];
        $queryType->fields [] = Parser::fieldDefinition(/** @lang GraphQL */ '
            node(id: ID! @globalId): Node @field(resolver: "Nuwave\\\Lighthouse\\\GlobalId\\\NodeRegistry@resolve")
        ');
    }

    /**
     * Returns whether or not the given interface is used within the defined types.
     */
    protected function hasTypeImplementingInterface(DocumentAST $documentAST, string $interfaceName): bool
    {
        foreach ($documentAST->types as $typeDefinition) {
            if (
                $typeDefinition instanceof ObjectTypeDefinitionNode
                && ASTHelper::typeImplementsInterface($typeDefinition, $interfaceName)
            ) {
                return true;
            }
        }

        return false;
    }
}
