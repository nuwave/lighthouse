<?php

namespace Nuwave\Lighthouse\Schema\Generators;

class SchemaGenerator
{
    /**
     * Generate relay schema.json file.
     *
     * @param  string  $version
     * @return bool
     */
    public function build($version = '4.12')
    {
        $query = file_get_contents(__DIR__.'/../../../assets/introspection-'.$version.'.txt');
        $data = app('graphql')->execute($query);

        if (isset($data['data']['__schema'])) {
            $schema = json_encode($data);
            $path = config('lighthouse.schema.output') ?: storage_path('relay/schema.json');

            $this->put($path, $schema);
        }

        return $data;
    }

    /**
     * Put to a file path.
     *
     * @param  string  $path
     * @param  string  $contents
     * @return mixed
     */
    protected function put($path, $contents)
    {
        $this->makeDirectory(dirname($path));

        return file_put_contents($path, $contents);
    }

    /**
     * Make a directory tree recursively.
     *
     * @param  string  $dir
     * @return void
     */
    protected function makeDirectory($dir)
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }
}
