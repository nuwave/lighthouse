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
        $this->schema = /** @lang GraphQL */ '
        type User @key(fields: "id") {
          id: ID! @external
        }
        ';

        $user = factory(User::class)->create();

        $userRepresentation = [
            '__typename' => 'User',
            'id' => (string) $user->getKey(),
        ];

        $this->graphQL(/** @lang GraphQL */ '
        query ($representations: [_Any!]!) {
            _entities(representations: $representations) {
                __typename
                ... on User {
                    id
                }
            }
        }
        ', [
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
        $this->schema = /** @lang GraphQL */ '
        type User @key(fields: "company { id }") {
          id: ID! @external
          company: Company! @belongsTo
        }

        type Company {
            id: ID!
        }
        ';

        $company = factory(Company::class)->create();
        assert($company instanceof Company);

        $user = factory(User::class)->make();
        assert($user instanceof User);
        $user->id = $company->id + 1;
        $user->company()->associate($company);
        $user->save();

        $userRepresentation = [
            '__typename' => 'User',
            'company' => [
                'id' => (string) $company->id,
            ],
        ];

        $this->graphQL(/** @lang GraphQL */ '
        query ($representations: [_Any!]!) {
            _entities(representations: $representations) {
                __typename
                ... on User {
                    id
                }
            }
        }
        ', [
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
        $this->schema = /** @lang GraphQL */ '
        type User @key(fields: "id") {
          id: ID! @external @globalId
        }
        ';

        $user = factory(User::class)->create();

        $userRepresentation = [
            '__typename' => 'User',
            'id' => $this->app->get(GlobalId::class)->encode('User', $user->getKey()),
        ];

        $this->graphQL(/** @lang GraphQL */ '
        query ($representations: [_Any!]!) {
            _entities(representations: $representations) {
                __typename
                ... on User {
                    id
                }
            }
        }
        ', [
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
        $this->schema = /** @lang GraphQL */ '
        type User @key(fields: "id") {
          id: ID!
          externallyProvided: String! @external
        }
        ';

        $user = factory(User::class)->create();
        assert($user instanceof User);

        $userRepresentation = [
            '__typename' => 'User',
            'id' => (string) $user->id,
            'externallyProvided' => 'some value that we know nothing about',
        ];

        $this->graphQL(/** @lang GraphQL */ '
        query ($representations: [_Any!]!) {
            _entities(representations: $representations) {
                __typename
                ... on User {
                    id
                    externallyProvided
                }
            }
        }
        ', [
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
