<?php

namespace Nuwave\Lighthouse\Schema\Utils;

class SchemaStitcher
{
    /**
     * Stitch together schema documents.
     *
     * @param string      $globalId
     * @param string|null $path
     *
     * @return string
     */
    public function stitch($globalId, $path = null)
    {
        $lighthouse = $this->lighthouseSchema($globalId);
        $app = $path ? $this->appSchema($path) : '';

        return $lighthouse.$app;
    }

    /**
     * Get Lighthouse schema.
     *
     * @param string $globalId
     *
     * @return string
     */
    public function lighthouseSchema($globalId = '_id')
    {
        $schema = file_get_contents(realpath(__DIR__.'/../../../assets/schema.graphql'));

        if ($globalId) {
            $node = file_get_contents(realpath(__DIR__.'/../../../assets/node.graphql'));

            return str_replace('_id', $globalId, $node).$schema;
        }

        return $schema;
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
