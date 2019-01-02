<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Tests\DBTestCase;
use Illuminate\Support\Arr;
use Tests\Utils\Models\Role;
use Tests\Utils\Models\User;
use Nuwave\Lighthouse\Exceptions\DirectiveException;

class BelongsToManyDirectiveTest extends DBTestCase
{
    /**
     * Auth user.
     *
     * @var User
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

    protected function setUp()
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
        $this->roles = factory(Role::class, $this->rolesCount)->create();

        $this->user
            ->roles()
            ->attach($this->roles);

        $this->be($this->user);
    }

    /**
     * @test
     */
    public function itCanQueryBelongsToManyRelationship()
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

        $result = $this->query('
        {
            user {
                roles {
                    id
                }
            }
        }
        ');

        $rolesCount = auth()
            ->user()
            ->roles()
            ->count();

        $this->assertSame($this->rolesCount, $rolesCount);
        $this->assertCount($this->rolesCount, Arr::get($result->data, 'user.roles'));
    }

    /**
     * @test
     */
    public function itCanQueryBelongsToManyPaginator()
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

        $result = $this->query('
        {
            user {
                roles(count: 2) {
                    paginatorInfo {
                        total
                        count
                        hasMorePages
                    }
                    data {
                        id
                    }
                }
            }
        }
        ');

        $this->assertSame(2, Arr::get($result->data, 'user.roles.paginatorInfo.count'));
        $this->assertSame($this->rolesCount, Arr::get($result->data, 'user.roles.paginatorInfo.total'));
        $this->assertTrue(Arr::get($result->data, 'user.roles.paginatorInfo.hasMorePages'));
        $this->assertCount(2, Arr::get($result->data, 'user.roles.data'));
    }

    /**
     * @test
     */
    public function itCanQueryBelongsToManyRelayConnection()
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

        $result = $this->query('
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
        ');

        $this->assertTrue(Arr::get($result->data, 'user.roles.pageInfo.hasNextPage'));
        $this->assertCount(2, Arr::get($result->data, 'user.roles.edges'));
    }

    /**
     * @test
     */
    public function itCanQueryBelongsToManyNestedRelationships()
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

        $result = $this->query('
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

        $userRolesEdges = Arr::get($result->data, 'user.roles.edges');
        $nestedUserRolesEdges = Arr::get($result->data, 'user.roles.edges.0.node.users.0.roles.edges');

        $this->assertTrue(Arr::get($result->data, 'user.roles.pageInfo.hasNextPage'));
        $this->assertCount(2, $userRolesEdges);
        $this->assertCount(2, $nestedUserRolesEdges);
        $this->assertSame(Arr::get($userRolesEdges, 'node.0.acl.id'), Arr::get($nestedUserRolesEdges, 'node.0.acl.id'));
        $this->assertSame(Arr::get($userRolesEdges, 'node.1.acl.id'), Arr::get($nestedUserRolesEdges, 'node.1.acl.id'));
    }

    /**
     * @test
     */
    public function itThrowsErrorWithUnknownTypeArg()
    {
        $this->expectException(DirectiveException::class);

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
