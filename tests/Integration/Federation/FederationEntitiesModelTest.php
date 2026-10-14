<?php declare(strict_types=1);

namespace Tests\Integration\Federation;

use Nuwave\Lighthouse\Federation\FederationServiceProvider;
use Nuwave\Lighthouse\GlobalId\GlobalId;
use Tests\DBTestCase;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\User;

final class FederationEntitiesModelTest extends DBTestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(
            parent::getPackageProviders($app),
            [FederationServiceProvider::class],
        );
    }

    public function testCallsEntityResolverModel(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User @key(fields: "id") {
          id: ID! @external
        }
        GRAPHQL;

        $user = factory(User::class)->create();

        $userRepresentation = [
            '__typename' => 'User',
            'id' => (string) $user->getKey(),
        ];

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($representations: [_Any!]!) {
            _entities(representations: $representations) {
                __typename
                ... on User {
                    id
                }
            }
        }
        GRAPHQL, [
            'representations' => [
                $userRepresentation,
            ],
        ])->assertExactJson([
            'data' => [
                '_entities' => [
                    $userRepresentation,
                ],
            ],
        ]);
    }

    public function testCallsNestedEntityResolverModel(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User @key(fields: "company { id }") {
          id: ID! @external
          company: Company! @belongsTo
        }

        type Company {
            id: ID!
        }
        GRAPHQL;

        $company = factory(Company::class)->create();
        $this->assertInstanceOf(Company::class, $company);

        $user = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $user);
        $user->id = $company->id + 1;
        $user->company()->associate($company);
        $user->save();

        $userRepresentation = [
            '__typename' => 'User',
            'company' => [
                'id' => (string) $company->id,
            ],
        ];

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($representations: [_Any!]!) {
            _entities(representations: $representations) {
                __typename
                ... on User {
                    id
                }
            }
        }
        GRAPHQL, [
            'representations' => [
                $userRepresentation,
            ],
        ])->assertExactJson([
            'data' => [
                '_entities' => [
                    [
                        '__typename' => 'User',
                        'id' => (string) $user->id,
                    ],
                ],
            ],
        ]);
    }

    public function testCallsEntityResolverModelWithGlobalId(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User @key(fields: "id") {
          id: ID! @external @globalId
        }
        GRAPHQL;

        $user = factory(User::class)->create();

        $userRepresentation = [
            '__typename' => 'User',
            'id' => $this->app->get(GlobalId::class)->encode('User', $user->getKey()),
        ];

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($representations: [_Any!]!) {
            _entities(representations: $representations) {
                __typename
                ... on User {
                    id
                }
            }
        }
        GRAPHQL, [
            'representations' => [
                $userRepresentation,
            ],
        ])->assertExactJson([
            'data' => [
                '_entities' => [
                    $userRepresentation,
                ],
            ],
        ]);
    }

    public function testHydratesExternalFields(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User @key(fields: "id") {
          id: ID!
          externallyProvided: String! @external
        }
        GRAPHQL;

        $user = factory(User::class)->create();
        $this->assertInstanceOf(User::class, $user);

        $userRepresentation = [
            '__typename' => 'User',
            'id' => (string) $user->id,
            'externallyProvided' => 'some value that we know nothing about',
        ];

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($representations: [_Any!]!) {
            _entities(representations: $representations) {
                __typename
                ... on User {
                    id
                    externallyProvided
                }
            }
        }
        GRAPHQL, [
            'representations' => [
                $userRepresentation,
            ],
        ])->assertExactJson([
            'data' => [
                '_entities' => [
                    $userRepresentation,
                ],
            ],
        ]);
    }
}
