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
                $documentAST->setTypeDefinition(self::simplePaginatorInfo());
                $documentAST->setTypeDefinition(self::pageInfo());
            }
        );

        $dispatcher->listen(
            RegisterDirectiveNamespaces::class,
            static function (): string {
                return __NAMESPACE__;
            }
        );
    }

    protected static function paginatorInfo(): ObjectTypeDefinitionNode
    {
        return Parser::objectTypeDefinition(/** @lang GraphQL */ '
            "Information about pagination using a fully featured paginator."
            type PaginatorInfo {
              "Number of items in the current page."
              count: Int!

              "Index of the current page."
              currentPage: Int!

              "Index of the first item in the current page."
              firstItem: Int

              "Are there more pages after this one?"
              hasMorePages: Boolean!

              "Index of the last item in the current page."
              lastItem: Int

              "Index of the last available page."
              lastPage: Int!

              "Number of items per page."
              perPage: Int!

              "Number of total available items."
              total: Int!
            }
        ');
    }

    protected static function simplePaginatorInfo(): ObjectTypeDefinitionNode
    {
        return Parser::objectTypeDefinition(/** @lang GraphQL */ '
            "Information about pagination using a simple paginator."
            type SimplePaginatorInfo {
              "Number of items in the current page."
              count: Int!

              "Index of the current page."
              currentPage: Int!

              "Index of the first item in the current page."
              firstItem: Int

              "Index of the last item in the current page."
              lastItem: Int

              "Number of items per page."
              perPage: Int!

              "Are there more pages after this one?"
              hasMorePages: Boolean!
            }
        ');
    }

    protected static function pageInfo(): ObjectTypeDefinitionNode
    {
        return Parser::objectTypeDefinition(/** @lang GraphQL */ '
            "Information about pagination using a Relay style cursor connection."
            type PageInfo {
              "When paginating forwards, are there more items?"
              hasNextPage: Boolean!

              "When paginating backwards, are there more items?"
              hasPreviousPage: Boolean!

              "The cursor to continue paginating backwards."
              startCursor: String

              "The cursor to continue paginating forwards."
              endCursor: String

              "Total number of nodes in the paginated connection."
              total: Int!

              "Number of nodes in the current page."
              count: Int!

              "Index of the current page."
              currentPage: Int!

              "Index of the last available page."
              lastPage: Int!
            }
        ');
    }
}
