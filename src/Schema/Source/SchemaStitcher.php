<?php

namespace Nuwave\Lighthouse\Schema\Source;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SchemaStitcher implements SchemaSourceProvider
{
    /**
     * @var string
     */
    protected $rootSchemaPath;

    public function __construct(string $rootSchemaPath)
    {
        $this->rootSchemaPath = $rootSchemaPath;
    }

    /**
     * Set schema root path.
     *
     * @return $this
     */
    public function setRootPath(string $path): self
    {
        $this->rootSchemaPath = $path;

        return $this;
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
        if (! file_exists($path)) {
            self::throwFileNotFoundException($path);
        }

        return (new Collection(file($path)))
            ->map(function (string $line) use ($path): string {
                if (! Str::startsWith(trim($line), '#import ')) {
                    return rtrim($line, PHP_EOL).PHP_EOL;
                }

                $importFileName = trim(Str::after($line, '#import '));
                $importFilePath = dirname($path).'/'.$importFileName;

                if (! Str::contains($importFileName, '*')) {
                    $realPath = realpath($importFilePath);

                    if ($realPath === false) {
                        self::throwFileNotFoundException($importFilePath);
                    }
                    /** @var string $realPath */

                    return self::gatherSchemaImportsRecursively($realPath);
                }

                $importFilePaths = glob($importFilePath);

                return (new Collection($importFilePaths))
                    ->map(function ($file): string {
                        return self::gatherSchemaImportsRecursively($file);
                    })
                    ->implode('');
            })
            ->implode('');
    }

    /**
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected static function throwFileNotFoundException(string $path): void
    {
        throw new FileNotFoundException(
            "Failed to find a GraphQL schema file at {$path}. If you just installed Lighthouse, run php artisan vendor:publish --provider=\"Nuwave\Lighthouse\Providers\LighthouseServiceProvider\" --tag=schema"
        );
    }
}
