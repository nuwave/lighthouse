<?php

namespace Tests\Integration\Schema\Directives;

use function factory;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\DBTestCase;
use Tests\Utils\Models\Book;

class SumDirectiveDBTest extends DBTestCase
{
    public function testRequiresAModelArgument(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            books_sum_price: Int @sum
        }
        ';

        $this->expectException(DefinitionException::class);
        $this->graphQL(/** @lang GraphQL */ '
        {
            books_sum_price
        }
        ');
    }

    public function testSumOfColumnForModel(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            books_sum_price: Int @sum(model: "Book", column: "price")
        }
        ';

        factory(Book::class)->create(['price'=>10]);
        factory(Book::class)->create(['price'=>20]);
        factory(Book::class)->create(['price'=>30]);

        $this->graphQL(/** @lang GraphQL */ '
        {
            books_sum_price
        }
        ')->assertExactJson([
            'data' => [
                'books_sum_price' => 60,
            ],
        ]);
    }

    public function testResolveSumByModel(): void
    {
        factory(Book::class)->create(['price'=>10]);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            books_sum_price: Int! @sum(model: "Book", column: "price")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            books_sum_price
        }
        ')->assertJson([
            'data' => [
                'books_sum_price' => 10,
            ],
        ]);
    }

    public function testResolveSumByColumn(): void
    {
        factory(Book::class)->create(['price'=>10]);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            books: [Book!] @all
        }

        type Book {
            sum_price: Int! @sum(column: "price")
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            books {
               sum_price
            }
        }
        ')->assertJson([
            'data' => [
                'books'=> [
                    ['sum_price' => 10],
                ],
            ],
        ]);
    }
}
