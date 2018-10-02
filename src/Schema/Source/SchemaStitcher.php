<?php

namespace Nuwave\Lighthouse\Schema\Source;

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
     * @return SchemaSourceProvider
     */
    public function setRootPath(string $path): SchemaStitcher
    {
        $this->rootSchemaPath = $path;

        return $this;
    }

    /**
     * Stitch together schema documents and return the result as a string.
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
     * @return string
     */
    protected static function gatherSchemaImportsRecursively(string $path): string
    {
        // This will throw if no file is found at this location
        return collect(file($path))
            ->map(function (string $line) use ($path) {
                if (! starts_with(trim($line), '#import ')) {
                    return $line;
                }

                $importFileName = trim(str_after($line, '#import '));

                if (! str_contains($importFileName, '*')) {
                    $importFilePath = realpath(dirname($path).'/'.$importFileName);

                    return self::gatherSchemaImportsRecursively($importFilePath);
                }

                $importFilePaths = glob(dirname($path) . '/' . $importFileName);
                return collect($importFilePaths)
                    ->map(function ($file) {
                        return self::gatherSchemaImportsRecursively($file);
                    })
                    ->implode('');
            })
            ->implode('');
    }
}
