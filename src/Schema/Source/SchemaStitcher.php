<?php

namespace Nuwave\Lighthouse\Schema\Source;

use Illuminate\Contracts\Filesystem\FileNotFoundException;

class SchemaStitcher implements SchemaSourceProvider
{
    /**
     * @var string
     */
    protected $rootSchemaPath;

    /**
     * SchemaStitcher constructor.
     *
     * @param string $rootSchemaPath
     */
    public function __construct(string $rootSchemaPath)
    {
        $this->rootSchemaPath = $rootSchemaPath;
    }

    /**
     * Set schema root path.
     *
     * @param string $path
     *
     * @return SchemaStitcher
     */
    public function setRootPath(string $path): SchemaStitcher
    {
        $this->rootSchemaPath = $path;

        return $this;
    }

    /**
     * Stitch together schema documents and return the result as a string.
     *
     * @throws FileNotFoundException
     *
     * @return string
     */
    public function getSchemaString(): string
    {
        return self::gatherSchemaImportsRecursively($this->rootSchemaPath);
    }

    /**
     * Get the schema, starting from a root schema, following the imports recursively.
     *
     * @param string $path
     *
     * @throws FileNotFoundException
     *
     * @return string
     */
    protected static function gatherSchemaImportsRecursively(string $path): string
    {
        if (! file_exists($path)) {
            self::throwFileNotFoundException($path);
        }

        return collect(file($path))
            ->map(function (string $line) use ($path) {
                if (! starts_with(trim($line), '#import ')) {
                    return rtrim($line, PHP_EOL).PHP_EOL;
                }

                $importFileName = trim(str_after($line, '#import '));
                $importFilePath = dirname($path).'/'.$importFileName;

                if (! str_contains($importFileName, '*')) {
                    $realPath = realpath($importFilePath);

                    if (! $realPath) {
                        self::throwFileNotFoundException($importFilePath);
                    }

                    return self::gatherSchemaImportsRecursively($realPath);
                }

                $importFilePaths = glob($importFilePath);

                return collect($importFilePaths)
                    ->map(function ($file) {
                        return self::gatherSchemaImportsRecursively($file);
                    })
                    ->implode('');
            })
            ->implode('');
    }

    /**
     * @param string $path
     *
     * @throws FileNotFoundException
     */
    protected static function throwFileNotFoundException(string $path)
    {
        throw new FileNotFoundException(
            "Failed to find a GraphQL schema file at {$path}. If you just installed Lighthouse, run php artisan vendor:publish --provider=\"Nuwave\Lighthouse\Providers\LighthouseServiceProvider\" --tag=schema"
        );
    }
}
