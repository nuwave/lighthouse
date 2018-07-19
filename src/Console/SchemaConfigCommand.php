<?php

namespace Nuwave\Lighthouse\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SchemaConfigCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = '
        lighthouse:schema:config
        {--W|write : Generate new schema file}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate graphql-config file.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('write')) {
            $this->call('lighthouse:schema:print', ['--write' => 1]);
        }

        $configFile = config('lighthouse.schema.config_file', base_path('.graphqlconfig'));
        $schemaPath = config(
            'lighthouse.schema.output',
            storage_path('app/lighthouse-schema.graphql')
        );

        $config = array_merge([
            'schemaPath' => $schemaPath,
            'extensions' => [
                'endpoints' => [
                    'dev' => route('lighthouse.graphql'),
                ],
            ],
        ], config('lighthouse.schema.config_options', []));

        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));

        $this->info(sprintf(
            'Wrote graphql config to defined file: %s',
            $configFile
        ));
    }
}
