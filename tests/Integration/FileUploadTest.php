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
    ' . self::PLACEHOLDER_QUERY;

    /**
     * https://github.com/jaydenseric/graphql-multipart-request-spec#single-file.
     */
    public function testResolvesUploadViaMultipartRequest(): void
    {
        $this->multipartGraphQL(
            [
                'operations' => /** @lang JSON */ '
                    {
                        "query": "mutation Upload($file: Upload!) { upload(file: $file) }",
                        "variables": {
                            "file": null
                        }
                    }
                ',
                'map' => /** @lang JSON */ '
                    {
                        "0": ["variables.file"]
                    }
                ',
            ],
            [
                '0' => UploadedFile::fake()->create('image.jpg', 500),
            ]
        )->assertJson([
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
        $this->multipartGraphQL(
            [
                'operations' => /** @lang JSON */ '
                    [
                        {
                            "query": "mutation Upload($file: Upload!) { upload(file: $file) }",
                            "variables": {
                                "file": null
                            }
                        },
                        {
                            "query": "mutation Upload($file: Upload!) { upload(file: $file)} ",
                            "variables": {
                                "file": null
                            }
                        }
                    ]
                ',
                'map' => /** @lang JSON */ '
                    {
                        "0": ["0.variables.file"],
                        "1": ["1.variables.file"]
                    }
                ',
            ],
            [
                '0' => UploadedFile::fake()->create('image.jpg', 500),
                '1' => UploadedFile::fake()->create('image.jpg', 500),
            ]
        )->assertJson([
            [
                'data' => [
                    'upload' => true,
                ],
            ],
            [
                'data' => [
                    'upload' => true,
                ],
            ],
        ]);
    }

    public function testResolvesQueryViaMultipartRequest(): void
    {
        $this->multipartGraphQL(
            [
                'operations' => /** @lang JSON */ '
                    {
                        "query": "{ foo }",
                        "variables": {}
                    }
                ',
                'map' => /** @lang JSON */ '{}',
            ],
            []
        )->assertJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);
    }

    public function testResolvesUploadViaArrayMultipartRequest(): void
    {
        $operations = [
            'operationName' => 'upload',
            'query' => 'mutation upload ($file: Upload!) {
                            upload (file: $file)
                        }',
            'variables' => [
                'file' => null
            ]
        ];

        $map = [
            '0' => ['variables.file']
        ];

        $file = [
            '0' => UploadedFile::fake()->create('test.pdf', 500)
        ];

        $response = $this->multipartArrayGraphQL($operations, $map, $file);
        $response->assertJson([
            'data' => [
                'upload' => true,
            ],
        ]);
    }

    public function testResolvesUploadViaBatchedArrayMultipartRequest(): void
    {
        $operations = [
            'operationName' => 'upload',
            'query' => 'mutation upload ($file1: Upload!, $file2: Upload!) {
                            first: upload (file: $file1)
                            second: upload (file: $file2)
                        }',
            'variables' => [
                'file1' => null,
                'file2' => null
            ]
        ];

        $map = [
            '0' => ['variables.file1'],
            '1' => ['variables.file2']
        ];

        $files = [
            '0' => UploadedFile::fake()->create('test.pdf', 500),
            '1' => UploadedFile::fake()->create('test.pdf', 500)
        ];

        $response = $this->multipartArrayGraphQL($operations, $map, $files);
        $response->assertJson([
            'data' => [
                'first' => true,
                'second' => true
            ],
        ]);
    }

    public function testResolvesQueryViaArrayMultipartRequest(): void
    {
        $operations = [
            'query' => '{ foo }',
            'variables' => []
        ];

        $map = [
            '0' => []
        ];

        $file = [
            '0' => []
        ];

        $response = $this->multipartArrayGraphQL($operations, $map, $file);
        $response->assertJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);
    }
}
