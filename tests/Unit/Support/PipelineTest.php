<?php

namespace Tests\Unit\Support;

use Illuminate\Contracts\Support\Responsable;
use Tests\DBTestCase;
use Tests\TestCase;
use Tests\Utils\Models\Post;

class PipelineTest extends DBTestCase
{
    public function testDoesNotCallToResponse(): void
    {
        $post = factory(Post::class)->create(['title' => 'bar']);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(id: ID @eq): Post @find
        }

        type Post {
            id: ID!
            title: String
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(id: 1) {
                id
                title
            }
        }
        ')->assertExactJson([
            'data' => [
                'foo' => [
                    'title' => 42,
                ],
            ],
        ]);
    }
}
