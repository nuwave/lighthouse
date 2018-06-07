<?php

namespace Nuwave\Lighthouse\Schema\AST;

class SchemaStitcher
{
    /**
     * Stitch together schema documents.
     *
     * @param string|null $path
     *
     * @return string
     */
    public function stitch($path = null)
    {
        $lighthouse = file_get_contents(realpath(__DIR__.'/../../../assets/schema.graphql'));

        $app = $path ? $this->appSchema($path) : '';

        return $lighthouse.$app;
    }

    /**
     * Get application schema.
     *
     * @param string $path
     *
     * @return string
     */
    protected function appSchema($path)
    {
        try {
            $schema = file_get_contents($path);
        } catch (\Exception $e) {
            // TODO: Publish demo/startup file with a minimal.
            return '';
        }

        $imports = collect(explode("\n", $schema))->filter(function ($line) {
            return 0 === strpos(trim($line), '#import');
        })->map(function ($import) {
            return trim(str_replace('#import', '', $import));
        })->map(function ($file) use ($path) {
            return $this->appSchema(realpath(dirname($path).'/'.$file));
        })->implode("\n");

        return $imports."\n".$schema;
    }
}
