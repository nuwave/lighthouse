<?php declare(strict_types=1);

namespace Tests\Integration\Auth;

use Tests\DBTestCase;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\User;
use Tests\Utils\Policies\UserPolicy;

final class CanResolvedDirectiveDBTest extends DBTestCase
{
    public function testChecksAgainstResolvedModelsFromPaginator(): void
    {
        $user = new User();
        $user->name = UserPolicy::ADMIN;
        $this->be($user);

        $user = factory(User::class)->create();

        $this->schema = /** @lang GraphQL */ '
        type Query {
            users: [User!]!
                @canResolved(ability: "view")
                @paginate
        }

        type User {
            name: String
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            users(first: 2) {
                data {
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'users' => [
                    'data' => [
                        [
                            'name' => $user->name,
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testChecksAgainstRelation(): void
    {
        $user = new User();
        $user->name = UserPolicy::ADMIN;
        $this->be($user);

        $company = factory(Company::class)->create();

        $user = factory(User::class)->make();
        assert($user instanceof User);
        $user->company()->associate($company);
        $user->save();

        $this->schema = /** @lang GraphQL */ '
        type Query {
            company: Company @first
        }

        type Company {
            users: [User!]!
                @canResolved(ability: "view")
                @hasMany
        }

        type User {
            name: String
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            company {
                users {
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'company' => [
                    'users' => [
                        [
                            'name' => $user->name,
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testChecksAgainstMissingResolvedModelWithFind(): void
    {
        $user = new User();
        $user->name = UserPolicy::ADMIN;
        $this->be($user);

        $user = factory(User::class)->create();

        $this->schema = /** @lang GraphQL */ '
        type Query {
            user(id: ID! @whereKey): User
                @canResolved(ability: "view")
                @find
        }

        type User {
            name: String!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user(id: "not-present") {
                name
            }
        }
        ')->assertJson([
            'data' => [
                'user' => null,
            ],
            'errors' => [
                [
                    'message' => 'This action is unauthorized.',
                ],
            ],
        ]);
    }
}
