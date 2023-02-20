<?php

namespace Nuwave\Lighthouse\Schema\Source;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Safe\Exceptions\FilesystemException;

class SchemaStitcher implements SchemaSourceProvider
{
    /**
     * @var string
     */
    protected $rootSchemaPath;

    public function __construct(string $rootSchemaPath)
    {
        if (! file_exists($rootSchemaPath)) {
            throw new FileNotFoundException(
                "Failed to find a GraphQL schema file at {$rootSchemaPath}. If you just installed Lighthouse, run php artisan vendor:publish --tag=lighthouse-schema"
            );
        }

        $this->rootSchemaPath = $rootSchemaPath;
    }

    /**
     * Stitch together schema documents and return the result as a string.
     */
    public function getSchemaString(): string
    {
        return self::gatherSchemaImportsRecursively($this->rootSchemaPath);
    }

    /**
     * Get the schema, starting from a root schema, following the imports recursively.
     */
    protected static function gatherSchemaImportsRecursively(string $path): string
    {
        return (new Collection(\Safe\file($path)))
            ->map(function (string $line) use ($path): string {
                if (! Str::startsWith(trim($line), '#import ')) {
                    return rtrim($line, PHP_EOL) . PHP_EOL;
                }

                $importFileName = trim(Str::after($line, '#import '));
                $importFilePath = dirname($path) . '/' . $importFileName;

                if (! Str::contains($importFileName, '*')) {
                    try {
                        $realpath = \Safe\realpath($importFilePath);
                    } catch (FilesystemException $exception) {
                        throw new FileNotFoundException(
                            "Did not find GraphQL schema import at {$importFilePath}.",
                            $exception->getCode(),
                            $exception
                        );
                    }

                    return self::gatherSchemaImportsRecursively($realpath);
                }

                return (new Collection(\Safe\glob($importFilePath)))
                    ->map(function ($file): string {
                        return self::gatherSchemaImportsRecursively($file);
                    })
                    ->implode('');
            })
            ->implode('');
    }
}
