<?php

namespace Tests\Unit\Schema\Directives;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class UploadDirectiveTest extends TestCase
{
    private const DISK_NAME = 'uploadDisk';

    protected $operations = [
        'query' => /** @lang GraphQL */ '
                mutation ($file: Upload!) {
                    file: upload(file: $file)
                }
            ',
        'variables' => [
            'file' => null,
        ],
    ];

    public function testUploadArgumentWithDefaultParameters(): void
    {
        config()->offsetSet('filesystems.default', self::DISK_NAME);

        $filePath = null;

        $this->mockResolver(static function ($root, array $args) use(&$filePath): ?string {
            $filePath = $args['file'];
            return $args['file'];
        });

        Storage::fake(self::DISK_NAME);

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
            $this->operations,
            ['0' => ['variables.file']],
            ['0' => $file]
        )->assertJson([
            'data' => [
                'file' => $filePath,
            ],
        ]);

        Storage::disk(self::DISK_NAME)->assertExists($file->hashName());
    }

    public function testUploadArgumentWithDiskParameter(): void
    {
        Storage::fake(self::DISK_NAME);

        $filePath = null;

        $this->mockResolver(static function ($root, array $args) use(&$filePath): ?string {
            $filePath = $args['file'];
            return $args['file'];
        });

        $this->schema = /** @lang GraphQL */ '
        scalar Upload @scalar(class: "Nuwave\\\\Lighthouse\\\\Schema\\\\Types\\\\Scalars\\\\Upload")
    
        type Mutation {
            upload(
            file: Upload! @upload(disk:"' . self::DISK_NAME . '")
            ): String @mock
        }
        ' . self::PLACEHOLDER_QUERY;

        $file = UploadedFile::fake()->create('test.pdf', 500);

        $this->multipartGraphQL(
            $this->operations,
            ['0' => ['variables.file']],
            ['0' => $file]
        )->assertJson([
            'data' => [
                'file' => $filePath,
            ],
        ]);

        Storage::disk(self::DISK_NAME)->assertExists($file->hashName());
    }

    public function testUploadArgumentWithPathParameter(): void
    {
        config()->offsetSet('filesystems.default', self::DISK_NAME);
        Storage::fake(self::DISK_NAME);

        $filePath = null;

        $this->mockResolver(static function ($root, array $args) use(&$filePath): ?string {
            $filePath = $args['file'];
            return $args['file'];
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
            $this->operations,
            ['0' => ['variables.file']],
            ['0' => $file]
        )->assertJson([
            'data' => [
                'file' => $filePath,
            ],
        ]);

        Storage::disk(self::DISK_NAME)->assertExists("/test/{$file->hashName()}");
    }

    public function testUploadArgumentWhereValueIsNull(): void
    {
        $filePath = null;

        $this->mockResolver(static function ($root, array $args) use(&$filePath): ?string {
            $filePath = $args['file'];
            return $args['file'];
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
        $this->expectException(Exception::class);

        config()->offsetSet('filesystems.default', self::DISK_NAME);

        $this->schema = /** @lang GraphQL */ '
        scalar Upload @scalar(class: "Nuwave\\\\Lighthouse\\\\Schema\\\\Types\\\\Scalars\\\\Upload")
    
        type Mutation {
            upload(
            file: Upload! @upload(disk:"' . self::DISK_NAME . '")
            ): Boolean
        }
        ' . self::PLACEHOLDER_QUERY;

        $file = UploadedFile::fake()->create('test.pdf', 500);

        $this->multipartGraphQL(
            $this->operations,
            ['0' => ['variables.file']],
            ['0' => $file]
        );
    }

    public function testThrowsAnExceptionIfAttributeIsNotUploadedFile(): void
    {
        $this->expectException(Exception::class);

        $this->schema = /** @lang GraphQL */ '
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
