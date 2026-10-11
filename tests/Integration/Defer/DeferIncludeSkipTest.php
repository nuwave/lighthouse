<?php declare(strict_types=1);

namespace Tests\Integration\Defer;

use Nuwave\Lighthouse\Defer\DeferServiceProvider;
use Tests\TestCase;

final class DeferIncludeSkipTest extends TestCase
{
    protected string $schema = /** @lang GraphQL */ <<<'GRAPHQL'
    directive @include(if: Boolean!) on FIELD
    directive @skip(if: Boolean!) on FIELD
    GRAPHQL . "\n" . self::PLACEHOLDER_QUERY;

    protected function getPackageProviders($app): array
    {
        return array_merge(
            parent::getPackageProviders($app),
            [DeferServiceProvider::class],
        );
    }

    public function testDoesNotDeferWithIncludeFalse(): void
    {
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo @defer @include(if: false)
        }
        GRAPHQL)->assertExactJson([
            'data' => [],
        ]);
    }

    public function testDoesDeferWithIncludeTrue(): void
    {
        $chunks = $this->streamGraphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo @defer @include(if: true)
        }
        GRAPHQL);

        $this->assertCount(2, $chunks);
    }

    public function testDoesNotDeferWithIncludeFalseFromVariable(): void
    {
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($include: Boolean!) {
            foo @defer @include(if: $include)
        }
        GRAPHQL, [
            'include' => false,
        ])->assertExactJson([
            'data' => [],
        ]);
    }

    public function testDoesNotDeferWithSkipTrue(): void
    {
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo @defer @skip(if: true)
        }
        GRAPHQL)->assertExactJson([
            'data' => [],
        ]);
    }

    public function testDoesDeferWithSkipFalse(): void
    {
        $chunks = $this->streamGraphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo @defer @skip(if: false)
        }
        GRAPHQL);

        $this->assertCount(2, $chunks);
    }

    public function testDoesNotDeferWithSkipTrueFromVariable(): void
    {
        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($skip: Boolean!) {
            foo @defer @skip(if: $skip)
        }
        GRAPHQL, [
            'skip' => true,
        ])->assertExactJson([
            'data' => [],
        ]);
    }
}
