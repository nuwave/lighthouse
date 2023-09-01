<?php declare(strict_types=1);

namespace Tests\Integration\Pennant;

use Illuminate\Testing\TestResponse;
use Laravel\Pennant\Feature;
use Nuwave\Lighthouse\Testing\MocksResolvers;
use Nuwave\Lighthouse\Testing\UsesTestSchema;
use Tests\TestCase;

final class FeatureDirectiveTest extends TestCase
{
    use UsesTestSchema;
    use MocksResolvers;

    /** @before */
    public function setUpSchema(): void
    {
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
type Query {
    fieldWhenFeatureInactive: String! 
        @feature(name: "foo", when: INACTIVE)
        @mock
    fieldWhenFeatureActive: String!
        @feature(name: "foo", when: ACTIVE)
        @mock
    fieldWhenFeatureActiveByDefault: String!
        @feature(name: "foo")
        @mock 
}
GRAPHQL;
    }

    /** @after */
    public function unsetSchema(): void
    {
        unset($this->schema);
    }

    public function testUnavailableWhenFeatureIsInactive(): void
    {
        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query {
                fieldWhenFeatureActive
                fieldWhenFeatureActiveByDefault
            }
            GRAPHQL,
        );

        $this->assertCannotQueryFieldErrorMessage($response, 'fieldWhenFeatureActive', 'fieldWhenFeatureInactive');
        $this->assertCannotQueryFieldErrorMessage(
            $response,
            'fieldWhenFeatureActiveByDefault',
            'fieldWhenFeatureInactive',
        );
    }

    public function testUnavailableWhenFeatureIsActive(): void
    {
        Feature::define('foo', fn () => true);

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query {
                fieldWhenFeatureInactive
            }
            GRAPHQL,
        );

        $this->assertCannotQueryFieldErrorMessage($response, 'fieldWhenFeatureInactive', 'fieldWhenFeatureActive');
    }

    public function testAvailableWhenFeatureIsActive(): void
    {
        Feature::define('foo', fn () => true);
        $this->mockResolver(fn (): string => 'active');

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query {
                fieldWhenFeatureActive
                fieldWhenFeatureActiveByDefault
            }
            GRAPHQL,
        );

        $response->assertGraphQLErrorFree();
        $response->assertJson([
            'data' => [
                'fieldWhenFeatureActive' => 'active',
                'fieldWhenFeatureActiveByDefault' => 'active',
            ],
        ]);
    }

    public function testAvailableWhenFeatureIsInactive(): void
    {
        $this->mockResolver(fn (): string => 'inactive');

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query {
                fieldWhenFeatureInactive
            }
            GRAPHQL,
        );

        $response->assertGraphQLErrorFree();
        $response->assertJson([
            'data' => [
                'fieldWhenFeatureInactive' => 'inactive',
            ],
        ]);
    }

    private function assertCannotQueryFieldErrorMessage(
        TestResponse $response,
        string $expected,
        string $suggested,
    ): void {
        $response->assertGraphQLErrorMessage(
            "Cannot query field \"{$expected}\" on type \"Query\". Did you mean \"{$suggested}\"?",
        );
    }
}
