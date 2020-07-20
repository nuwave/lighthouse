<?php

namespace Tests\Integration\Defer;

use Nuwave\Lighthouse\Defer\DeferServiceProvider;
use Tests\TestCase;

class DeferIncludeSkipTest extends TestCase
{
    protected $schema = /** @lang GraphQL */ '
    directive @include(if: Boolean!) on FIELD
    directive @skip(if: Boolean!) on FIELD
    '.self::PLACEHOLDER_QUERY;

    protected function getPackageProviders($app): array
    {
        return array_merge(
            parent::getPackageProviders($app),
            [DeferServiceProvider::class]
        );
    }

    public function testDoesNotDeferWithIncludeFalse(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        {
            foo @defer @include(if: false)
        }
        ')->assertExactJson([
            'data' => [],
        ]);
    }

    public function testDoesDeferWithIncludeTrue(): void
    {
        $chunks = $this->streamGraphQL(/** @lang GraphQL */ '
        {
            foo @defer @include(if: true)
        }
        ');

        $this->assertCount(2, $chunks);
    }

    public function testDoesNotDeferWithIncludeFalseFromVariable(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        query ($include: Boolean!){
            foo @defer @include(if: $include)
        }
        ', [
            'include' => false,
        ])->assertExactJson([
            'data' => [],
        ]);
    }

    public function testDoesNotDeferWithSkipTrue(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        {
            foo @defer @skip(if: true)
        }
        ')->assertExactJson([
            'data' => [],
        ]);
    }

    public function testDoesDeferWithSkipFalse(): void
    {
        $chunks = $this->streamGraphQL(/** @lang GraphQL */ '
        {
            foo @defer @skip(if: false)
        }
        ');

        $this->assertCount(2, $chunks);
    }

    public function testDoesNotDeferWithSkipTrueFromVariable(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        query ($skip: Boolean!){
            foo @defer @skip(if: $skip)
        }
        ', [
            'skip' => true,
        ])->assertExactJson([
            'data' => [],
        ]);
    }
}
