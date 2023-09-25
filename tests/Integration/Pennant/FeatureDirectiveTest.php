<?php declare(strict_types=1);

namespace Tests\Integration\Pennant;

use Illuminate\Testing\TestResponse;
use Laravel\Pennant\Feature;
use Laravel\Pennant\PennantServiceProvider as LaravelPennantServiceProvider;
use Nuwave\Lighthouse\Pennant\PennantServiceProvider as LighthousePennantServiceProvider;
use Nuwave\Lighthouse\Support\AppVersion;
use Nuwave\Lighthouse\Testing\MocksResolvers;
use Nuwave\Lighthouse\Testing\UsesTestSchema;
use Tests\TestCase;

final class FeatureDirectiveTest extends TestCase
{
    use UsesTestSchema;
    use MocksResolvers;

    protected function setUp(): void
    {
        parent::setUp();

        if (AppVersion::below(10)) {
            $this->markTestSkipped('Requires laravel/pennant, which requires Laravel 10');
        }
    }

    protected function getPackageProviders($app): array
    {
        if (AppVersion::below(10)) {
            return parent::getPackageProviders($app);
        }

        return array_merge(
            parent::getPackageProviders($app),
            [
                LaravelPennantServiceProvider::class,
                LighthousePennantServiceProvider::class,
            ],
        );
    }

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
            {
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
            {
                fieldWhenActive
            }
            GRAPHQL,
        );

        $this->assertCannotQueryFieldErrorMessage($response, 'fieldWhenActive');
    }

    public function testUnavailableWhenFeatureIsActive(): void
    {
        Feature::define('new-api', static fn (): bool => true);
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type Query {
                fieldWhenInactive: String!
                    @feature(name: "new-api", when: INACTIVE)
                    @mock
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            {
                fieldWhenInactive
            }
            GRAPHQL,
        );

        $this->assertCannotQueryFieldErrorMessage($response, 'fieldWhenInactive');
    }

    public function testAvailableWhenFeatureIsActive(): void
    {
        Feature::define('new-api', static fn (): bool => true);
        $fieldValue = 'active';
        $this->mockResolver(static fn (): string => $fieldValue);
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type Query {
                fieldWhenActive: String!
                    @feature(name: "new-api", when: ACTIVE)
                    @mock
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            {
                fieldWhenActive
            }
            GRAPHQL,
        );

        $response->assertGraphQLErrorFree();
        $response->assertJson([
            'data' => [
                'fieldWhenActive' => $fieldValue,
            ],
        ]);
    }

    public function testAvailableWhenFeatureIsActiveWithDefaultFeatureState(): void
    {
        Feature::define('new-api', static fn (): bool => true);
        $fieldValue = 'active';
        $this->mockResolver(static fn (): string => $fieldValue);
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type Query {
                fieldWhenActive: String!
                    @feature(name: "new-api")
                    @mock
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            {
                fieldWhenActive
            }
            GRAPHQL,
        );

        $response->assertGraphQLErrorFree();
        $response->assertJson([
            'data' => [
                'fieldWhenActive' => $fieldValue,
            ],
        ]);
    }

    public function testAvailableWhenFeatureIsInactive(): void
    {
        $fieldValue = 'inactive';
        $this->mockResolver(static fn (): string => $fieldValue);
        $this->schema = /* @lang GraphQL */ <<<'GRAPHQL'
            type Query {
                fieldWhenInactive: String!
                    @feature(name: "new-api", when: INACTIVE)
                    @mock
            }
            GRAPHQL;

        $response = $this->graphQL(/* @lang GraphQL */ <<<'GRAPHQL'
            {
                fieldWhenInactive
            }
            GRAPHQL,
        );

        $response->assertGraphQLErrorFree();
        $response->assertJson([
            'data' => [
                'fieldWhenInactive' => $fieldValue,
            ],
        ]);
    }

    private function assertCannotQueryFieldErrorMessage(TestResponse $response, string $expected): void
    {
        $response->assertGraphQLErrorMessage("Cannot query field \"{$expected}\" on type \"Query\".");
    }
}
