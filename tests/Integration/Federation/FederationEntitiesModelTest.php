<?php

namespace Tests\Integration\Federation;

use GraphQL\Error\Error;
use GraphQL\Utils\Utils;
use Nuwave\Lighthouse\Federation\EntityResolverProvider;
use Nuwave\Lighthouse\Federation\FederationServiceProvider;
use Nuwave\Lighthouse\Federation\Types\Any;
use Nuwave\Lighthouse\Support\Contracts\GlobalId;
use Tests\DBTestCase;
use Tests\TestCase;
use Tests\Utils\Models\User;

final class FederationEntitiesModelTest extends DBTestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(
            parent::getPackageProviders($app),
            [FederationServiceProvider::class]
        );
    }

    public function testCallsEntityResolverModel(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User @model @key(fields: "id") {
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
                    $userRepresentation
                ]
            ]
        ]);
    }

    public function testCallsEntityResolverModelWithGlobalId(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User @model @key(fields: "id") {
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
                    $userRepresentation
                ]
            ]
        ]);
    }
}
