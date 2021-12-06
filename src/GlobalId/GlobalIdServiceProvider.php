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
    public const NODE = 'Node';

    public function register(): void
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

                // Only add the node type and node field if a type actually implements them.
                // If we were to add it regardless, a validation error is thrown because an
                // interface without implementations is pointless to have in the schema.
                if ($this->hasTypeImplementingNodeInterface($documentAST)) {
                    $this->addNodeSupport($documentAST);
                }
            }
        );

        $dispatcher->listen(
            RegisterDirectiveNamespaces::class,
            static function (): string {
                return __NAMESPACE__;
            }
        );
    }

    protected function addNodeSupport(DocumentAST $documentAST): void
    {
        $node = self::NODE;
        $globalId = config('lighthouse.global_id_field');

        // Double slashes to escape the slashes in the namespace.
        $documentAST->setTypeDefinition(
            Parser::interfaceTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
"Any object implementing this type can be found by ID through `Query.node`."
interface $node @interface(resolveType: "Nuwave\\\Lighthouse\\\GlobalId\\\NodeRegistry@resolveType") {
  "Global identifier that can be used to resolve any Node implementation."
  $globalId: ID!
}
GRAPHQL
            )
        );

        /** @var \GraphQL\Language\AST\ObjectTypeDefinitionNode $queryType */
        $queryType = $documentAST->types[RootType::QUERY];
        $queryType->fields[] = Parser::fieldDefinition(/** @lang GraphQL */ <<<'GRAPHQL'
  node(id: ID! @globalId): Node @field(resolver: "Nuwave\\Lighthouse\\GlobalId\\NodeRegistry@resolve")
GRAPHQL
        );
    }

    protected function hasTypeImplementingNodeInterface(DocumentAST $documentAST): bool
    {
        foreach ($documentAST->types as $typeDefinition) {
            if (
                $typeDefinition instanceof ObjectTypeDefinitionNode
                && ASTHelper::typeImplementsInterface($typeDefinition, self::NODE)
            ) {
                return true;
            }
        }

        return false;
    }
}
