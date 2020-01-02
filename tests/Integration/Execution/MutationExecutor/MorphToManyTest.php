<?php

namespace Tests\Integration\Execution\MutationExecutor;

use Faker\Provider\Lorem;
use Tests\DBTestCase;
use Tests\Utils\Models\Role;
use Tests\Utils\Models\Tag;
use Tests\Utils\Models\User;

class MorphToManyTest extends DBTestCase
{
    protected $schema = '
    type Mutation {
        createTask(input: CreateTaskInput! @spread): Task @create
        upsertTask(input: UpsertTaskInput! @spread): Task @upsert
        updateUser(input: UpdateUserInput! @spread): User @update
    }

    input CreateTaskInput {
        name: String!
        tags: CreateTagRelation
    }

    input CreateTagRelation {
        create: [CreateTagInput!]
        upsert: [UpsertTagInput!]
        sync: [ID!]
        connect: [ID!]
    }

    input CreateTagInput {
        name: String!
    }

    input UpsertTaskInput {
        id: ID!
        name: String!
        tags: UpsertTagRelation
    }

    input UpsertTagRelation {
        create: [CreateTagInput!]
        upsert: [UpsertTagInput!]
        sync: [ID!]
        connect: [ID!]
    }

    input UpsertTagInput {
        id: ID!
        name: String!
    }

    type Task {
        id: ID!
        name: String!
        tags: [Tag!]!
    }

    type Tag {
        id: ID!
        name: String!
    }

    input UpdateUserInput {
        id: ID!
        roles: UpdateRoleRelation
    }

    input UpdateRoleRelation {
        sync: [UpdateUserRolePivot!]
        syncWithoutDetaching: [UpdateUserRolePivot!]
        connect: [UpdateUserRolePivot!]
    }

    input UpdateUserRolePivot {
        id: ID! # role ID
        meta: String
    }

    type User {
        id: ID!
        roles: [Role!]!
    }

    type Role {
        id: ID!
        pivot: UserRolePivot
    }

    type UserRolePivot {
        meta: String
    }

    '.self::PLACEHOLDER_QUERY;

    public function testCanCreateATaskWithExistingTagsByUsingConnect(): void
    {
        $id = factory(Tag::class)->create(['name' => 'php'])->id;

        $this->graphQL('
        mutation {
            createTask(input: {
                name: "Finish tests"
                tags: {
                    connect: [1]
                }
            }) {
                tags{
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'createTask' => [
                    'tags' => [
                        [
                            'id' => $id,
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testCanUpsertATaskWithExistingTagsByUsingConnect(): void
    {
        $id = factory(Tag::class)->create(['name' => 'php'])->id;

        $this->graphQL('
        mutation {
            upsertTask(input: {
                id: 1
                name: "Finish tests"
                tags: {
                    connect: [1]
                }
            }) {
                tags{
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'upsertTask' => [
                    'tags' => [
                        [
                            'id' => $id,
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testCanCreateATaskWithExistingTagsByUsingSync(): void
    {
        $id = factory(Tag::class)->create(['name' => 'php'])->id;

        $this->graphQL('
        mutation {
            createTask(input: {
                name: "Finish tests"
                tags: {
                    sync: [1]
                }
            }) {
                tags {
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'createTask' => [
                    'tags' => [
                        [
                            'id' => $id,
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testCanConnectUserWithRoleAndPivotMetaByUsingSync(): void
    {
        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();
        $role2 = factory(Role::class)->create();
        $user->roles()->attach($role2);

        $metaText = Lorem::sentence();

        $this->graphQL('
        mutation {
            updateUser(input: {
                id: 1,
                roles: {
                    sync: [
                        {
                            id: 1,
                            meta: "'.$metaText.'"
                        }
                    ]
                },
            }) {
                roles {
                    pivot {
                        meta
                    }
                }
            }
        }
        ')->assertJson([
            'data' => [
                'updateUser' => [
                    'roles' => [
                        [
                            'pivot' => [
                                'meta' => $metaText,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testCanConnectUserWithRoleAndPivotMetaByUsingSyncWithoutDetach(): void
    {
        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();
        $role2 = factory(Role::class)->create();
        $user->roles()->attach($role2);

        $metaText = Lorem::sentence();

        $this->graphQL('
        mutation {
            updateUser(input: {
                id: 1,
                roles: {
                    syncWithoutDetaching: [
                        {
                            id: 1,
                            meta: "'.$metaText.'"
                        }
                    ]
                },
            }) {
                roles {
                    pivot {
                        meta
                    }
                }
            }
        }
        ')->assertJson([
            'data' => [
                'updateUser' => [
                    'roles' => [
                        [
                            'pivot' => [
                                'meta' => $metaText,
                            ],
                        ],
                        [
                            'pivot' => [
                                'meta' => null,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testCanConnectUserWithRoleAndPivotMetaByUsingConnect(): void
    {
        $user = factory(User::class)->create();
        $role = factory(Role::class)->create();

        $metaText = Lorem::sentence();

        $this->graphQL('
        mutation {
            updateUser(input: {
                id: 1,
                roles: {
                    connect: [
                        {
                            id: 1,
                            meta: "'.$metaText.'"
                        }
                    ]
                },
            }) {
                roles {
                    pivot {
                        meta
                    }
                }
            }
        }
        ')->assertJson([
            'data' => [
                'updateUser' => [
                    'roles' => [
                        [
                            'pivot' => [
                                'meta' => $metaText,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testCanUpsertATaskWithExistingTagsByUsingSync(): void
    {
        $id = factory(Tag::class)->create(['name' => 'php'])->id;

        $this->graphQL('
        mutation {
            upsertTask(input: {
                id: 1
                name: "Finish tests"
                tags: {
                    sync: [1]
                }
            }) {
                tags {
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'upsertTask' => [
                    'tags' => [
                        [
                            'id' => $id,
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testCanCreateANewTagRelationByUsingCreate(): void
    {
        $this->graphQL('
        mutation {
            createTask(input: {
                name: "Finish tests"
                tags: {
                    create: [
                        {
                            name: "php"
                        }
                    ]
                }
            }) {
                tags {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'createTask' => [
                    'tags' => [
                        [
                            'id' => 1,
                            'name' => 'php',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testCanUpsertANewTagRelationByUsingCreate(): void
    {
        $this->graphQL('
        mutation {
            upsertTask(input: {
                id: 1
                name: "Finish tests"
                tags: {
                    create: [
                        {
                            name: "php"
                        }
                    ]
                }
            }) {
                tags {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'upsertTask' => [
                    'tags' => [
                        [
                            'id' => 1,
                            'name' => 'php',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testCanUpsertANewTagRelationByUsingUpsert(): void
    {
        $this->graphQL('
        mutation {
            upsertTask(input: {
                id: 1
                name: "Finish tests"
                tags: {
                    upsert: [
                        {
                            id: 1
                            name: "php"
                        }
                    ]
                }
            }) {
                tags {
                    id
                    name
                }
            }
        }
        ')->assertJson([
            'data' => [
                'upsertTask' => [
                    'tags' => [
                        [
                            'id' => 1,
                            'name' => 'php',
                        ],
                    ],
                ],
            ],
        ]);
    }
}
