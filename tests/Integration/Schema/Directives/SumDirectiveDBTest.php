<?php

namespace Tests\Integration\Schema\Directives;

use Tests\Utils\Models\Book;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\DBTestCase;
use function factory;

class SumDirectiveDBTest extends DBTestCase
{
    public function testRequiresAModelOrRelationArgument(): void
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
}
