<?php

namespace Tests\Integration\Schema\Directives;

use GraphQL\Type\Definition\Type;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\DBTestCase;
use Tests\Utils\Models\Role;
use Tests\Utils\Models\User;

class BelongsToManyDirectiveTest extends DBTestCase
{
    /**
     * The authenticated user.
     *
     * @var \Tests\Utils\Models\User
     */
    protected $user;

    /**
     * Roles of the authenticated user.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $roles;

    /**
     * @var int
     */
    protected $rolesCount = 4;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
        $this->roles = factory(Role::class, $this->rolesCount)->create();

        $this->user->roles()->attach($this->roles, ['meta' => 'new']);

        $this->be($this->user);
    }

    public function testCanQueryBelongsToManyRelationship(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            roles: [Role!]! @belongsToMany
        }

        type Role {
            id: Int!
            name: String!
        }

        type Query {
            user: User @auth
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                roles {
                    id
                }
            }
        }
        ')->assertJsonCount($this->rolesCount, 'data.user.roles');
    }

    public function testCanNameRelationExplicitly(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            foo: [Role!] @belongsToMany(relation: "roles")
        }

        type Role {
            id: Int!
            name: String!
        }

        type Query {
            user: User @auth
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                foo {
                    id
                }
            }
        }
        ')->assertJsonCount($this->rolesCount, 'data.user.foo');
    }

    public function testCanQueryBelongsToManyPaginator(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            roles: [Role!]! @belongsToMany(type: PAGINATOR)
        }

        type Role {
            id: Int!
            name: String!
        }

        type Query {
            user: User @auth
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                roles(first: 2) {
                    paginatorInfo {
                        count
                        hasMorePages
                        total
                    }
                    data {
                        id
                    }
                }
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'roles' => [
                        'paginatorInfo' => [
                            'count' => 2,
                            'hasMorePages' => true,
                            'total' => $this->rolesCount,
                        ],
                    ],
                ],
            ],
        ])->assertJsonCount(2, 'data.user.roles.data');
    }

    public function testCanQueryBelongsToManyRelayConnection(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            roles: [Role!]! @belongsToMany(type: CONNECTION)
        }

        type Role {
            id: Int!
            name: String!
        }

        type Query {
            user: User @auth
        }
        ';

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

    public function testCanQueryBelongsToManyRelayConnectionWithCustomEdgeUsingDirective(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            roles: [Role!]! @belongsToMany(type: CONNECTION, edgeType: "CustomRoleEdge")
        }

        type Role {
            id: Int!
            name: String!
        }

        type CustomRoleEdge {
            node: Role
            cursor: String!
            meta: String
        }

        type Query {
            user: User @auth
        }
        ';

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
                            [
                                'meta' => 'new',
                            ],
                        ],
                    ],
                ],
            ],
        ])->assertJsonCount(2, 'data.user.roles.edges');
    }

    public function testThrowsExceptionForInvalidEdgeTypeFromDirective(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            roles: [Role!]! @belongsToMany(type: CONNECTION, edgeType: "CustomRoleEdge")
        }

        type Role {
            id: Int!
            name: String!
        }

        type Query {
            user: User @auth
        }
        ';

        $this->expectException(DefinitionException::class);

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
        ');
    }

    public function testCanQueryBelongsToManyRelayConnectionWithCustomMagicEdge(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            roles: [Role!]! @belongsToMany(type: CONNECTION)
        }

        type Role {
            id: Int!
            name: String!
        }

        type RoleEdge {
            node: Role
            cursor: String!
            meta: String
            nofield: String
        }

        type Query {
            user: User @auth
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            user {
                roles(first: 2) {
                    edges {
                        meta
                        nofield
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
                            [
                                'meta' => 'new',
                                'nofield' => null,
                            ],
                        ],
                    ],
                ],
            ],
        ])->assertJsonCount(2, 'data.user.roles.edges');
    }

    public function testCanQueryBelongsToManyNestedRelationships(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            id: Int!
            roles: [Role!]! @belongsToMany(type: CONNECTION)
        }

        type ACL {
            id: Int!
            create_post: Boolean!
            read_post: Boolean!
            update_post: Boolean!
            delete_post: Boolean!
        }

        type Role {
            id: Int!
            name: String!
            acl: ACL @belongsTo
            users: [User]! @belongsToMany
        }

        type Query {
            user: User @auth
        }
        ';

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
        $this->expectExceptionMessage('Found invalid pagination type: foo');

        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ '
        type User {
            roles(first: Int! after: Int): [Role!]! @belongsToMany(type: "foo")
        }

        type Role {
            foo: String
        }
        ');

        $type = $schema->getType('User');

        $this->assertInstanceOf(Type::class, $type);
        /** @var \GraphQL\Type\Definition\Type $type */
        $type->config['fields']();
    }
}
