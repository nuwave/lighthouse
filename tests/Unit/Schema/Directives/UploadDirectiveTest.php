<?php

namespace Tests\Unit\Schema\Directives;

use Exception;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Mockery;
use Symfony\Component\HttpFoundation\File\Exception\CannotWriteFileException;
use Tests\TestCase;

final class UploadDirectiveTest extends TestCase
{
    public function testUploadArgumentWithDefaultParameters(): void
    {
        config(['filesystems.default' => 'uploadDisk']);

        $filePath = null;
        $this->mockResolver(static function ($root, array $args) use (&$filePath): ?string {
            return $filePath = $args['file'];
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

        $this->assertEquals('private', Storage::getVisibility($filePath));
    }

    public function testUploadArgumentWithDiskParameter(): void
    {
        Storage::fake('uploadDisk');

        $filePath = null;
        $this->mockResolver(static function ($root, array $args) use (&$filePath): ?string {
            return $filePath = $args['file'];
        });

        $this->schema = /** @lang GraphQL */ '
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
    }

    public function testUploadArgumentWithPathParameter(): void
    {
        config(['filesystems.default' => 'uploadDisk']);
        Storage::fake('uploadDisk');

        $filePath = null;
        $this->mockResolver(static function ($root, array $args) use (&$filePath): ?string {
            return $filePath = $args['file'];
        });

        $this->schema = /** @lang GraphQL */ '
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
    }

    public function testUploadArgumentWithPublicParameter(): void
    {
        config(['filesystems.default' => 'uploadDisk']);
        Storage::fake('uploadDisk');

        $filePath = null;
        $this->mockResolver(static function ($root, array $args) use (&$filePath): ?string {
            return $filePath = $args['file'];
        });

        $this->schema = /** @lang GraphQL */ '
        scalar Upload @scalar(class: "Nuwave\\\\Lighthouse\\\\Schema\\\\Types\\\\Scalars\\\\Upload")

        type Mutation {
            upload(
            file: Upload! @upload(public: true disk:"uploadDisk")
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

        $this->assertEquals('public', Storage::getVisibility($filePath));
    }

    public function testUploadArgumentWhereValueIsNull(): void
    {
        $filePath = null;
        $this->mockResolver(static function ($root, array $args) use (&$filePath): ?string {
            return $filePath = $args['file'];
        });

        $this->schema = /** @lang GraphQL */ '
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
        config(['filesystems.default' => 'uploadDisk']);

        $this->schema = /** @lang GraphQL */ '
        scalar Upload @scalar(class: "Nuwave\\\\Lighthouse\\\\Schema\\\\Types\\\\Scalars\\\\Upload")

        type Mutation {
            upload(
            file: Upload! @upload(disk:"uploadDisk")
            ): Boolean
        }
        ' . self::PLACEHOLDER_QUERY;

        $file = UploadedFile::fake()->create('test.pdf', 500);

        $this->expectException(Exception::class);

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
        config(['filesystems.default' => 'uploadDisk']);
        Storage::fake('uploadDisk');

        $this->schema = /** @lang GraphQL */ '
        scalar Upload @scalar(class: "Nuwave\\\\Lighthouse\\\\Schema\\\\Types\\\\Scalars\\\\Upload")

        type Mutation {
            upload(
            file: Upload! @upload(path:"/")
            ): String
        }
        ' . self::PLACEHOLDER_QUERY;

        $file = Mockery::mock(File::class);

        $file->shouldReceive('hashName')
            ->andReturn('testFileName.pdf');

        $file->shouldReceive('storeAs')
            ->andReturn(false);

        $this->expectExceptionObject(new CannotWriteFileException('Unable to upload `file` file to `/` via disk `uploadDisk`.'));

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

    public function testThrowsAnExceptionIfAttributeIsNotUploadedFile(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(
                baz: String @upload
            ): Boolean
        }
        ';

        $this->expectExceptionObject(new InvalidArgumentException('Expected argument `baz` to be instanceof Illuminate\\Http\\UploadedFile.'));
        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(baz: "something")
        }
        ');
    }
}
