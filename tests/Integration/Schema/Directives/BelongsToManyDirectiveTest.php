<?php

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Illuminate\Support\Arr;
use Tests\Utils\Models\Role;
use Tests\Utils\Models\User;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Exceptions\DefinitionException;

class BelongsToManyDirectiveTest extends DBTestCase
{
    /**
     * Auth user.
     *
     * @var \Tests\Utils\Models\User
     */
    protected $user;

    /**
     * User's tasks.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $roles;

    /**
     * @var int
     */
    protected $rolesCount = 4;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
        $this->roles = factory(Role::class, $this->rolesCount)->create();

        $this->user
            ->roles()
            ->attach(
                $this->roles,
                ['meta' => 'new']
            );

        $this->be($this->user);
    }

    /**
     * @test
     */
    public function itCanQueryBelongsToManyRelationship(): void
    {
        $this->schema = '
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

        $this->graphQL('
        {
            user {
                roles {
                    id
                }
            }
        }
        ')->assertJsonCount($this->rolesCount, 'data.user.roles');
    }

    /**
     * @test
     */
    public function itCanQueryBelongsToManyPaginator(): void
    {
        $this->schema = '
        type User {
            roles: [Role!]! @belongsToMany(type: "paginator")
        }
        
        type Role {
            id: Int!
            name: String!
        }
        
        type Query {
            user: User @auth
        }
        ';

        $this->graphQL('
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

    /**
     * @test
     */
    public function itCanQueryBelongsToManyRelayConnection(): void
    {
        $this->schema = '
        type User {
            roles: [Role!]! @belongsToMany(type: "relay")
        }
        
        type Role {
            id: Int!
            name: String!
        }
        
        type Query {
            user: User @auth
        }
        ';

        $this->graphQL('
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

    /**
     * @test
     */
    public function itCanQueryBelongsToManyRelayConnectionWithCustomEdgeUsingDirective(): void
    {
        $this->schema = '
        type User {
            roles: [Role!]! @belongsToMany(type: "relay", edgeType: "CustomRoleEdge")
        }
        
        type Role {
            id: Int!
            name: String!
        }
        
        type CustomRoleEdge implements Edge {
            node: Role
            cursor: String!
            meta: String
        }
        
        type Query {
            user: User @auth
        }
        ';

        $this->graphQL('
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

    /**
     * @test
     */
    public function itThrowsExceptionForEdgeTypeNotImplementingEdge(): void
    {
        $this->schema = '
        type User {
            roles: [Role!]! @belongsToMany(type: "relay")
        }
        
        type Role {
            id: Int!
            name: String!
        }
        
        type RoleEdge {
            node: Role
            cursor: String!
            meta: String
        }
        
        type Query {
            user: User @auth
        }
        ';

        $this->expectException(DefinitionException::class);

        $this->graphQL('
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

    /**
     * @test
     */
    public function itThrowsExceptionForInvalidEdgeTypeFromDirective(): void
    {
        $this->schema = '
        type User {
            roles: [Role!]! @belongsToMany(type: "relay", edgeType: "CustomRoleEdge")
        }
        
        type Role {
            id: Int!
            name: String!
        }
        
        type Query {
            user: User @auth
        }
        ';

        $this->expectException(DirectiveException::class);

        $this->graphQL('
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

    /**
     * @test
     */
    public function itCanQueryBelongsToManyRelayConnectionWithCustomMagicEdge(): void
    {
        $this->schema = '
        type User {
            roles: [Role!]! @belongsToMany(type: "relay")
        }
        
        type Role {
            id: Int!
            name: String!
        }
        
        type RoleEdge implements Edge {
            node: Role
            cursor: String!
            meta: String
            nofield: String
        }
        
        type Query {
            user: User @auth
        }
        ';

        $this->graphQL('
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

    /**
     * @test
     */
    public function itCanQueryBelongsToManyNestedRelationships(): void
    {
        $this->schema = '
        type User {
            id: Int!
            roles: [Role!]! @belongsToMany(type: "relay")
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

        $result = $this->graphQL('
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

        $this->assertTrue($result->jsonGet('data.user.roles.pageInfo.hasNextPage'));

        $userRolesEdges = $result->jsonGet('data.user.roles.edges');
        $nestedUserRolesEdges = $result->jsonGet('data.user.roles.edges.0.node.users.0.roles.edges');
        $this->assertCount(2, $userRolesEdges);
        $this->assertCount(2, $nestedUserRolesEdges);
        $this->assertSame(Arr::get($userRolesEdges, 'node.0.acl.id'), Arr::get($nestedUserRolesEdges, 'node.0.acl.id'));
        $this->assertSame(Arr::get($userRolesEdges, 'node.1.acl.id'), Arr::get($nestedUserRolesEdges, 'node.1.acl.id'));
    }

    /**
     * @test
     */
    public function itThrowsErrorWithUnknownTypeArg(): void
    {
        $this->expectExceptionMessageRegExp('/^Found invalid pagination type/');

        $schema = $this->buildSchemaWithPlaceholderQuery('
        type User {
            roles(first: Int! after: Int): [Role!]! @belongsToMany(type:"foo")
        }
        
        type Role {
            foo: String
        }
        ');

        $type = $schema->getType('User');
        $type->config['fields']();
    }
}
