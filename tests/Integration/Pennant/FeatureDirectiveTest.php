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

    public function testUnavailableWhenFeatureIsInactive(): void
    {
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type Query {
                fieldWhenActive: String!
                    @feature(name: "new-api", when: ACTIVE)
                    @mock
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query {
                fieldWhenActive
            }
            GRAPHQL,
        );

        $this->assertCannotQueryFieldErrorMessage($response, 'fieldWhenActive');
    }

    public function testUnavailableWhenFeatureIsInactiveWithDefaultFeatureState(): void
    {
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type Query {
                fieldWhenActive: String!
                    @feature(name: "new-api")
                    @mock
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query {
                fieldWhenActive
            }
            GRAPHQL,
        );

        $this->assertCannotQueryFieldErrorMessage($response, 'fieldWhenActive');
    }

    public function testUnavailableWhenFeatureIsActive(): void
    {
        Feature::define('new-api', fn () => true);
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type Query {
                fieldWhenInactive: String!
                    @feature(name: "new-api", when: INACTIVE)
                    @mock
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query {
                fieldWhenInactive
            }
            GRAPHQL,
        );

        $this->assertCannotQueryFieldErrorMessage($response, 'fieldWhenInactive');
    }

    public function testAvailableWhenFeatureIsActive(): void
    {
        Feature::define('new-api', fn () => true);
        $this->mockResolver(fn (): string => 'active');
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type Query {
                fieldWhenActive: String!
                    @feature(name: "new-api", when: ACTIVE)
                    @mock
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query {
                fieldWhenActive
            }
            GRAPHQL,
        );

        $response->assertGraphQLErrorFree();
        $response->assertJson([
            'data' => [
                'fieldWhenActive' => 'active',
            ],
        ]);
    }

    public function testAvailableWhenFeatureIsActiveWithDefaultFeatureState(): void
    {
        Feature::define('new-api', fn () => true);
        $this->mockResolver(fn (): string => 'active');
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type Query {
                fieldWhenActive: String!
                    @feature(name: "new-api")
                    @mock
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query {
                fieldWhenActive
            }
            GRAPHQL,
        );

        $response->assertGraphQLErrorFree();
        $response->assertJson([
            'data' => [
                'fieldWhenActive' => 'active',
            ],
        ]);
    }

    public function testAvailableWhenFeatureIsInactive(): void
    {
        $this->mockResolver(fn (): string => 'inactive');
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type Query {
                fieldWhenInactive: String!
                    @feature(name: "new-api", when: INACTIVE)
                    @mock
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            query {
                fieldWhenInactive
            }
            GRAPHQL,
        );

        $response->assertGraphQLErrorFree();
        $response->assertJson([
            'data' => [
                'fieldWhenInactive' => 'inactive',
            ],
        ]);
    }

    private function assertCannotQueryFieldErrorMessage(TestResponse $response, string $expected): void
    {
        $response->assertGraphQLErrorMessage(
            "Cannot query field \"{$expected}\" on type \"Query\".",
        );
    }
}
