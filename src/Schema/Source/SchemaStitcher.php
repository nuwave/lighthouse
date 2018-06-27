<?php

namespace Nuwave\Lighthouse\Schema\Source;

class SchemaStitcher implements SchemaSourceProvider
{
    /**
     * @var string
     */
    protected $rootSchemaPath = '';
    
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
     * Stitch together schema documents and return the result as a string.
     *
     * @return string
     */
    public function getSchemaString(): string
    {
        $lighthouseBaseSchema = file_get_contents(
            realpath(__DIR__ . '/../../../assets/schema.graphql')
        );
        
        $userDefinedSchema = self::getUserDefinedSchema(
            $this->rootSchemaPath
        );
        
        return
            $lighthouseBaseSchema
            . $userDefinedSchema;
    }
    
    /**
     * Get application schema.
     *
     * @param string $path
     *
     * @return string
     */
    protected static function getUserDefinedSchema(string $path): string
    {
        // This will throw if no file is found at this location
        $schema = file_get_contents($path);
        
        $imports = collect(explode(PHP_EOL, $schema))
            ->map(function (string $line){
                return trim($line);
            })->filter(function (string $line) {
                return starts_with($line, '#import ');
            })->map(function (string $importStatement) {
                return str_replace('#import ', '', $importStatement);
            })->map(function (string $importFileName) use ($path) {
                $importFilePath = realpath(dirname($path) . '/' . $importFileName);
                
                return self::getUserDefinedSchema($importFilePath);
            })->implode(PHP_EOL);
        
        return $imports . PHP_EOL . $schema;
    }
}
