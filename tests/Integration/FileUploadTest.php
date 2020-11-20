<?php

namespace Tests\Integration;

use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Tests\Utils\Queries\Foo;

class FileUploadTest extends TestCase
{
    protected $schema = /** @lang GraphQL */ '
    scalar Upload @scalar(class: "Nuwave\\\\Lighthouse\\\\Schema\\\\Types\\\\Scalars\\\\Upload")

    type Mutation {
        upload(file: Upload!): Boolean
    }
    '.self::PLACEHOLDER_QUERY;

    /**
     * https://github.com/jaydenseric/graphql-multipart-request-spec#single-file.
     */
    public function testResolvesUploadViaMultipartRequest(): void
    {
        $operations = [
            'query' => /** @lang GraphQL */'
                mutation ($file: Upload!) {
                    upload(file: $file)
                }
            ',
            'variables' => [
                'file' => null,
            ],
        ];

        $map = [
            '0' => ['variables.file'],
        ];

        $file = [
            '0' => UploadedFile::fake()->create('test.pdf', 500),
        ];

        $this
            ->multipartGraphQL($operations, $map, $file)
            ->assertJson([
                'data' => [
                    'upload' => true,
                ],
            ]);
    }

    /**
     * https://github.com/jaydenseric/graphql-multipart-request-spec#batching.
     */
    public function testResolvesUploadViaBatchedMultipartRequest(): void
    {
        $operations = [
            'query' => /** @lang GraphQL */ '
                mutation ($file1: Upload!, $file2: Upload!) {
                    first: upload(file: $file1)
                    second: upload(file: $file2)
                }
            ',
            'variables' => [
                'file1' => null,
                'file2' => null,
            ],
        ];

        $map = [
            '0' => ['variables.file1'],
            '1' => ['variables.file2'],
        ];

        $files = [
            '0' => UploadedFile::fake()->create('test.pdf', 500),
            '1' => UploadedFile::fake()->create('test.pdf', 500),
        ];

        $this
            ->multipartGraphQL($operations, $map, $files)
            ->assertJson([
                'data' => [
                    'first' => true,
                    'second' => true,
                ],
            ]);
    }

    public function testResolvesQueryViaMultipartRequest(): void
    {
        $operations = [
            'query' => '{ foo }',
            'variables' => [],
        ];

        $map = [
            '0' => [],
        ];

        $file = [
            '0' => [],
        ];

        $this
            ->multipartGraphQL($operations, $map, $file)
            ->assertJson([
                'data' => [
                    'foo' => Foo::THE_ANSWER,
                ],
            ]);
    }
}
