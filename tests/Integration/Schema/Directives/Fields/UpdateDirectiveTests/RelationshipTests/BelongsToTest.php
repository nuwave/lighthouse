<?php

namespace Tests\Integration\Schema\Directives\Fields\UpdateDirectiveTests\RelationshipTests;

use Tests\DBTestCase;
use Tests\Utils\Models\Post;

class BelongsToTest extends DBTestCase
{
    protected $schema = '
    type Mutation {
        updatePost(input: UpdatePostInput!): Post @update(flatten: true)
    }
    
    type Post {
        id: ID!
        title: String!
        parent: Post @belongsTo
    }
    
    input UpdatePostInput {
        id: ID!
        title: String
        parent: UpdatePostRelation
    }
    
    input UpdatePostRelation {
        disconnect: Boolean
        delete: Boolean
    }
    ';

    public function setUp(): void
    {
        parent::setUp();

        $this->schema .= $this->placeholderQuery();
    }

    /**
     * @test
     */
    public function itCanUpdateAndDisconnectBelongsTo(): void
    {
        /** @var \Tests\Utils\Models\Post $post */
        $post = factory(Post::class)->create();
        $post
            ->parent()
            ->associate(
                factory(Post::class)->create()
            )
            ->save();

        $this->query('
        mutation {
            updatePost(input: {
                id: 1
                title: "foo"
                parent: {
                    disconnect: true
                }
            }) {
                id
                title
                parent {
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'updatePost' => [
                    'id' => '1',
                    'title' => 'foo',
                    'parent' => null,
                ],
            ],
        ]);

        $this->assertTrue(
            Post::find(2)->exists,
            'Must not delete the second model.'
        );

        $this->assertNull(
            Post::find(1)->parent,
            'Must disconnect the parent relationship.'
        );
    }

    /**
     * @test
     */
    public function itCanUpdateAndDeleteBelongsTo(): void
    {
        /** @var \Tests\Utils\Models\Post $post */
        $post = factory(Post::class)->create();
        $post
            ->parent()
            ->associate(
                factory(Post::class)->create()
            )
            ->save();

        $this->query('
        mutation {
            updatePost(input: {
                id: 1
                title: "foo"
                parent: {
                    delete: true
                }
            }) {
                id
                title
                parent {
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'updatePost' => [
                    'id' => '1',
                    'title' => 'foo',
                    'parent' => null,
                ],
            ],
        ]);

        $this->assertNull(
            Post::find(2),
            'This model should be deleted.'
        );

        $this->assertNull(
            Post::find(1)->parent,
            'Must disconnect the parent relationship.'
        );
    }

    /**
     * @test
     */
    public function itDoesNotDeleteOrDisconnectOnFalsyValues(): void
    {
        /** @var \Tests\Utils\Models\Post $post */
        $post = factory(Post::class)->create();
        $post
            ->parent()
            ->associate(
                factory(Post::class)->create()
            )
            ->save();

        $this->query('
        mutation {
            updatePost(input: {
                id: 1
                title: "foo"
                parent: {
                    delete: null
                    disconnect: false
                }
            }) {
                id
                title
                parent {
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'updatePost' => [
                    'id' => '1',
                    'title' => 'foo',
                    'parent' => [
                        'id' => '2'
                    ],
                ],
            ],
        ]);

        $this->assertSame(
            2,
            Post::find(1)->parent->id,
            'The parent relationship remains untouched.'
        );
    }
}
