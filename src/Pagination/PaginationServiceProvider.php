<?php

namespace Nuwave\Lighthouse\Pagination;

use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;

class PaginationServiceProvider extends ServiceProvider
{
    public function boot(Dispatcher $dispatcher): void
    {
        $dispatcher->listen(
            ManipulateAST::class,
            function (ManipulateAST $manipulateAST): void {
                $documentAST = $manipulateAST->documentAST;
                $documentAST->setTypeDefinition(self::paginatorInfo());
                $documentAST->setTypeDefinition(self::pageInfo());
            }
        );

        $dispatcher->listen(
            RegisterDirectiveNamespaces::class,
            function (RegisterDirectiveNamespaces $registerDirectiveNamespaces): string {
                return __NAMESPACE__;
            }
        );
    }

    protected static function paginatorInfo(): ObjectTypeDefinitionNode
    {
        return Parser::objectTypeDefinition(/** @lang GraphQL */ '
            "Pagination information about the corresponding list of items."
            type PaginatorInfo {
              "Total count of available items in the page."
              count: Int!

              "Current pagination page."
              currentPage: Int!

              "Index of first item in the current page."
              firstItem: Int

              "If collection has more pages."
              hasMorePages: Boolean!

              "Index of last item in the current page."
              lastItem: Int

              "Last page number of the collection."
              lastPage: Int!

              "Number of items per page in the collection."
              perPage: Int!

              "Total items available in the collection."
              total: Int!
            }
        ');
    }

    protected static function pageInfo(): ObjectTypeDefinitionNode
    {
        return Parser::objectTypeDefinition(/** @lang GraphQL */ '
            "Pagination information about the corresponding list of items."
            type PageInfo {
              "When paginating forwards, are there more items?"
              hasNextPage: Boolean!

              "When paginating backwards, are there more items?"
              hasPreviousPage: Boolean!

              "When paginating backwards, the cursor to continue."
              startCursor: String

              "When paginating forwards, the cursor to continue."
              endCursor: String

              "Total number of node in connection."
              total: Int

              "Count of nodes in current request."
              count: Int

              "Current page of request."
              currentPage: Int

              "Last page in connection."
              lastPage: Int
            }
        ');
    }
}
