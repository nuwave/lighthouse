<?php

namespace Tests\Unit\Schema\Directives;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

final class UploadDirectiveTest extends TestCase
{
    public function testUploadArgumentWithDefaultParameters(): void
    {
        config()->offsetSet('filesystems.default', 'uploadDisk');

        $filePath = null;

        $this->mockResolver(static function ($root, array $args) use (&$filePath): ?string {
            $filePath = $args['file'];
            return $args['file'];
        });

        Storage::fake('uploadDisk');

        $this->schema = /** @lang GraphQL */ '
        scalar Upload @scalar(class: "Nuwave\\\\Lighthouse\\\\Schema\\\\Types\\\\Scalars\\\\Upload")
    
        type Mutation {
            upload(
            file: Upload! @upload
            ): String @mock
        }
        ' . self::PLACEHOLDER_QUERY;

        $file = UploadedFile::fake()->create('test.pdf', 500);

        $this->multipartGraphQL(
            [
                'query' => /** @lang GraphQL */ '
                    mutation ($file: Upload!) {
                        file: upload(file: $file)
                    }
                ',
                'variables' => [
                    'file' => null,
                ],
            ],
            ['0' => ['variables.file']],
            ['0' => $file]
        )->assertJson([
            'data' => [
                'file' => $filePath,
            ],
        ]);

        Storage::disk('uploadDisk')->assertExists($file->hashName());
    }

    public function testUploadArgumentWithDiskParameter(): void
    {
        Storage::fake('uploadDisk');

        $filePath = null;

        $this->mockResolver(static function ($root, array $args) use (&$filePath): ?string {
            $filePath = $args['file'];
            return $args['file'];
        });

        $this->schema = /** @lang GraphQL */
            '
        scalar Upload @scalar(class: "Nuwave\\\\Lighthouse\\\\Schema\\\\Types\\\\Scalars\\\\Upload")
    
        type Mutation {
            upload(
            file: Upload! @upload(disk:"uploadDisk")
            ): String @mock
        }
        ' . self::PLACEHOLDER_QUERY;

        $file = UploadedFile::fake()->create('test.pdf', 500);

        $this->multipartGraphQL(
            [
                'query' => /** @lang GraphQL */ '
                mutation ($file: Upload!) {
                    file: upload(file: $file)
                }
            ',
                'variables' => [
                    'file' => null,
                ],
            ],
            ['0' => ['variables.file']],
            ['0' => $file]
        )->assertJson([
            'data' => [
                'file' => $filePath,
            ],
        ]);

        Storage::disk('uploadDisk')->assertExists($file->hashName());
    }

    public function testUploadArgumentWithPathParameter(): void
    {
        config()->offsetSet('filesystems.default', 'uploadDisk');
        Storage::fake('uploadDisk');

        $filePath = null;

        $this->mockResolver(static function ($root, array $args) use (&$filePath): ?string {
            $filePath = $args['file'];
            return $args['file'];
        });

        $this->schema = /** @lang GraphQL */
            '
        scalar Upload @scalar(class: "Nuwave\\\\Lighthouse\\\\Schema\\\\Types\\\\Scalars\\\\Upload")
    
        type Mutation {
            upload(
            file: Upload! @upload(path:"test")
            ): String @mock
        }
        ' . self::PLACEHOLDER_QUERY;

        $file = UploadedFile::fake()->create('test.pdf', 500);

        $this->multipartGraphQL(
            [
                'query' => /** @lang GraphQL */ '
                mutation ($file: Upload!) {
                    file: upload(file: $file)
                }
            ',
                'variables' => [
                    'file' => null,
                ],
            ],
            ['0' => ['variables.file']],
            ['0' => $file]
        )->assertJson([
            'data' => [
                'file' => $filePath,
            ],
        ]);

        Storage::disk('uploadDisk')->assertExists("/test/{$file->hashName()}");
    }

    public function testUploadArgumentWhereValueIsNull(): void
    {
        $filePath = null;

        $this->mockResolver(static function ($root, array $args) use (&$filePath): ?string {
            $filePath = $args['file'];
            return $args['file'];
        });

        $this->schema = /** @lang GraphQL */
            '
        scalar Upload @scalar(class: "Nuwave\\\\Lighthouse\\\\Schema\\\\Types\\\\Scalars\\\\Upload")
    
        type Mutation {
            upload(
            file: Upload @upload
            ): String @mock
        }
        ' . self::PLACEHOLDER_QUERY;

        $this->multipartGraphQL(
            [
                'query' => /** @lang GraphQL */ '
                mutation ($file: Upload) {
                    file: upload(file: $file)
                }
            ',
                'variables' => [
                    'file' => null,
                ],
            ],
            ['0' => ['variables.file']],
            ['0' => []]
        )->assertJson([
            'data' => [
                'file' => $filePath,
            ],
        ]);
    }

    public function testThrowsAnExceptionIfDiskIsMissing(): void
    {
        $this->expectException(Exception::class);

        config()->offsetSet('filesystems.default', 'uploadDisk');

        $this->schema = /** @lang GraphQL */
            '
        scalar Upload @scalar(class: "Nuwave\\\\Lighthouse\\\\Schema\\\\Types\\\\Scalars\\\\Upload")
    
        type Mutation {
            upload(
            file: Upload! @upload(disk:"uploadDisk")
            ): Boolean
        }
        ' . self::PLACEHOLDER_QUERY;

        $file = UploadedFile::fake()->create('test.pdf', 500);

        $this->multipartGraphQL(
            [
                'query' => /** @lang GraphQL */ '
                mutation ($file: Upload!) {
                    file: upload(file: $file)
                }
            ',
                'variables' => [
                    'file' => null,
                ],
            ],
            ['0' => ['variables.file']],
            ['0' => $file]
        );
    }

    public function testThrowsAnExceptionIfStoringFails(): void
    {
        $this->expectExceptionMessage("Unable to upload `upload` file to `/` via disk `uploadDisk`");

        config()->offsetSet('filesystems.default', 'uploadDisk');

        $filePath = null;

        Storage::fake('uploadDisk');

        $this->schema = /** @lang GraphQL */
            '
        scalar Upload @scalar(class: "Nuwave\\\\Lighthouse\\\\Schema\\\\Types\\\\Scalars\\\\Upload")
    
        type Mutation {
            upload(
            file: Upload! @upload(path:"/")
            ): String
        }
        ' . self::PLACEHOLDER_QUERY;

        $file = Mockery::mock(UploadedFile::class);

        $file->shouldReceive('hashName')
            ->andReturn('testFileName.pdf');

        $file->shouldReceive('storeAs')
            ->andReturn(false);

        $this->multipartGraphQL(
            [
                'query' => /** @lang GraphQL */ '
                mutation ($file: Upload!) {
                    file: upload(file: $file)
                }
            ',
                'variables' => [
                    'file' => null,
                ],
            ],
            ['0' => ['variables.file']],
            ['0' => $file]
        )->assertJson([
            'data' => [
                'file' => $filePath,
            ],
        ]);

        Storage::disk('uploadDisk')->assertExists($file->hashName());
    }

    public function testThrowsAnExceptionIfAttributeIsNotUploadedFile(): void
    {
        $this->expectException(Exception::class);

        $this->schema = /** @lang GraphQL */
            '
        type Query {
            foo(
                baz: String @upload
            ): Boolean
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(baz: "something")
        }
        ');
    }
}
