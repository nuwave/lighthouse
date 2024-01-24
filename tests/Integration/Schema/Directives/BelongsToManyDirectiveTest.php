<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\DBTestCase;
use Tests\Utils\Models\Role;
use Tests\Utils\Models\User;

final class BelongsToManyDirectiveTest extends DBTestCase
{
    public function testQueryBelongsToManyRelationship(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            roles: [Role!]! @belongsToMany
        }

        type Role {
            id: ID!
        }

        type Query {
            user: User! @auth
        }
        ';

        $user = factory(User::class)->create();
        assert($user instanceof User);
        $this->be($user);

        $rolesCount = 2;
        $roles = factory(Role::class, $rolesCount)->create();
        $user->roles()->attach($roles);

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                roles {
                    id
                }
            }
        }
        ')->assertJsonCount($rolesCount, 'data.user.roles');
    }

    public function testNameRelationExplicitly(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            foo: [Role!] @belongsToMany(relation: "roles")
        }

        type Role {
            id: ID!
        }

        type Query {
            user: User! @auth
        }
        ';

        $user = factory(User::class)->create();
        assert($user instanceof User);
        $this->be($user);

        $rolesCount = 2;
        $roles = factory(Role::class, $rolesCount)->create();
        $user->roles()->attach($roles);

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                foo {
                    id
                }
            }
        }
        ')->assertJsonCount($rolesCount, 'data.user.foo');
    }

    public function testQueryBelongsToManyPaginator(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            rolesPaginated: [Role!]! @belongsToMany(type: PAGINATOR, relation: "roles")
            rolesSimplePaginated: [Role!]! @belongsToMany(type: SIMPLE, relation: "roles")
        }

        type Role {
            id: ID!
        }

        type Query {
            user: User! @auth
        }
        ';

        $user = factory(User::class)->create();
        assert($user instanceof User);
        $this->be($user);

        $rolesCount = 4;
        $roles = factory(Role::class, $rolesCount)->create();
        $user->roles()->attach($roles);

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                user {
                    rolesPaginated(first: 2) {
                        paginatorInfo {
                            count
                            hasMorePages
                            total
                        }
                        data {
                            id
                        }
                    }
                    rolesSimplePaginated(first: 3) {
                        paginatorInfo {
                            count
                        }
                        data {
                            id
                        }
                    }
                }
            }
            ')
            ->assertJson([
                'data' => [
                    'user' => [
                        'rolesPaginated' => [
                            'paginatorInfo' => [
                                'count' => 2,
                                'hasMorePages' => true,
                                'total' => $rolesCount,
                            ],
                        ],
                        'rolesSimplePaginated' => [
                            'paginatorInfo' => [
                                'count' => 3,
                            ],
                        ],
                    ],
                ],
            ])
            ->assertJsonCount(2, 'data.user.rolesPaginated.data')
            ->assertJsonCount(3, 'data.user.rolesSimplePaginated.data');
    }

    public function testQueryBelongsToManyRelayConnection(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            roles: [Role!]! @belongsToMany(type: CONNECTION)
        }

        type Role {
            id: ID!
        }

        type Query {
            user: User! @auth
        }
        ';

        $user = factory(User::class)->create();
        assert($user instanceof User);
        $this->be($user);

        $roles = factory(Role::class, 3)->create();
        $user->roles()->attach($roles);

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                roles(first: 2) {
                    pageInfo {
                        hasNextPage
                    }
                    edges {
                        node {
                            id
                        }
                    }
                }
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'roles' => [
                        'pageInfo' => [
                            'hasNextPage' => true,
                        ],
                    ],
                ],
            ],
        ])->assertJsonCount(2, 'data.user.roles.edges');
    }

    public function testQueryBelongsToManyRelayConnectionWithCustomEdgeUsingDirective(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            roles: [Role!]! @belongsToMany(type: CONNECTION, edgeType: "CustomRoleEdge")
        }

        type Role {
            id: ID!
        }

        type CustomRoleEdge {
            node: Role!
            cursor: String!
            meta: String
        }

        type Query {
            user: User! @auth
        }
        ';

        $user = factory(User::class)->create();
        assert($user instanceof User);
        $this->be($user);

        $roles = factory(Role::class, 3)->create();
        $meta = ['meta' => 'new'];
        $user->roles()->attach($roles, $meta);

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                roles(first: 2) {
                    edges {
                        meta
                        node {
                            id
                        }
                    }
                }
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'roles' => [
                        'edges' => [
                            $meta,
                        ],
                    ],
                ],
            ],
        ])->assertJsonCount(2, 'data.user.roles.edges');
    }

    public function testQueryBelongsToManyPivot(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            roles: [Role!]! @belongsToMany
        }

        type Role {
            id: ID!
            pivot: RoleUserPivot
        }

        type RoleUserPivot {
            meta: String
        }

        type Query {
            user: User! @auth
        }
        ';

        $user = factory(User::class)->create();
        assert($user instanceof User);
        $this->be($user);

        $rolesCount = 2;
        $roles = factory(Role::class, $rolesCount)->create();
        $meta = ['meta' => 'new'];
        $user->roles()->attach($roles, $meta);

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                roles {
                    id
                    pivot {
                        meta
                    }
                }
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'roles' => [
                        [
                            'pivot' => $meta,
                        ],
                    ],
                ],
            ],
        ])->assertJsonCount($rolesCount, 'data.user.roles');
    }

    public function testThrowsExceptionForInvalidEdgeTypeFromDirective(): void
    {
        $this->expectExceptionObject(new DefinitionException(
            'The `edgeType` argument of @belongsToMany on roles must reference an existing object type definition.',
        ));
        $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ '
        type User {
            roles: [Role!]! @belongsToMany(type: CONNECTION, edgeType: "CustomRoleEdge")
        }

        type Role {
            id: ID!
        }
        ');
    }

    public function testQueryBelongsToManyRelayConnectionWithCustomMagicEdge(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            roles: [Role!]! @belongsToMany(type: CONNECTION)
        }

        type Role {
            id: ID!
        }

        type RoleEdge {
            node: Role!
            cursor: String!
            meta: String
        }

        type Query {
            user: User! @auth
        }
        ';

        $user = factory(User::class)->create();
        assert($user instanceof User);
        $this->be($user);

        $roles = factory(Role::class, 3)->create();
        $meta = ['meta' => 'new'];
        $user->roles()->attach($roles, $meta);

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                roles(first: 2) {
                    edges {
                        meta
                        node {
                            id
                        }
                    }
                }
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'roles' => [
                        'edges' => [
                            $meta,
                        ],
                    ],
                ],
            ],
        ])->assertJsonCount(2, 'data.user.roles.edges');
    }

    public function testQueryPaginatedBelongsToManyWithDuplicates(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            users: [User]! @all
        }

        type User {
            id: ID!
            roles: [Role!]! @belongsToMany(type: SIMPLE)
        }

        type Role {
            id: ID!
        }
        ';

        $roles = factory(Role::class, 2)->create();

        $users = factory(User::class, 2)->create();
        foreach ($users as $user) {
            assert($user instanceof User);
            $user->roles()->attach($roles);
        }

        $roleIDs = $roles
            ->map(static fn (Role $role): array => ['id' => (string) $role->id])
            ->all();

        $this->graphQL(/** @lang GraphQL */ '
        {
            users {
                id
                roles(first: 2) {
                    data {
                        id
                    }
                }
            }
        }
        ')->assertJson([
            'data' => [
                'users' => $users
                    ->map(static fn (User $user): array => [
                        'id' => (string) $user->id,
                        'roles' => [
                            'data' => $roleIDs,
                        ],
                    ])
                    ->all(),
            ],
        ]);
    }

    public function testQueryBelongsToManyNestedRelationships(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            id: ID!
            roles: [Role!]! @belongsToMany(type: CONNECTION)
        }

        type Role {
            id: ID!
            acl: ACL @belongsTo
            users: [User!]! @belongsToMany
        }

        type ACL {
            id: ID!
        }

        type Query {
            user: User! @auth
        }
        ';

        $user = factory(User::class)->create();
        assert($user instanceof User);
        $this->be($user);

        $roles = factory(Role::class, 3)->create();
        $user->roles()->attach($roles);

        $result = $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                roles(first: 2) {
                    pageInfo {
                        hasNextPage
                    }
                    edges {
                        node {
                            id
                            acl {
                                id
                            }
                            users {
                                id
                                roles(first: 2) {
                                    edges {
                                        node {
                                            id
                                            acl {
                                                id
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        ');

        $this->assertTrue($result->json('data.user.roles.pageInfo.hasNextPage'));

        $userRolesEdges = $result->json('data.user.roles.edges');
        $nestedUserRolesEdges = $result->json('data.user.roles.edges.0.node.users.0.roles.edges');
        $this->assertCount(2, $userRolesEdges);
        $this->assertCount(2, $nestedUserRolesEdges);
        $this->assertSame(Arr::get($userRolesEdges, 'node.0.acl.id'), Arr::get($nestedUserRolesEdges, 'node.0.acl.id'));
        $this->assertSame(Arr::get($userRolesEdges, 'node.1.acl.id'), Arr::get($nestedUserRolesEdges, 'node.1.acl.id'));
    }

    public function testThrowsErrorWithUnknownTypeArg(): void
    {
        $this->expectExceptionObject(new DefinitionException('Found invalid pagination type: foo'));
        $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ '
        type User {
            roles(first: Int! after: Int): [Role!]! @belongsToMany(type: "foo")
        }

        type Role {
            id: ID!
        }
        ');
    }
}
