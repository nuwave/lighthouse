<?php

namespace Nuwave\Relay\Support\Console\Commands;

use Illuminate\Console\Command;
use Nuwave\Relay\Schema\Generators\SchemaGenerator;

class SchemaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'relay:schema';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a new Relay schema.';

    /**
     * Relay schema generator.
     *
     * @var SchemaGenerator
     */
    protected $generator;

    /**
     * Create a new command instance.
     *
     * @param SchemaGenerator $generator
     */
    public function __construct(SchemaGenerator $generator)
    {
        $this->generator = $generator;

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $data = $this->generator->build();

        if (!isset($data['data']['__schema'])) {
            $this->error('There was an error when attempting to generate the schema file.');
            $this->line(json_encode($data));
        }

        $this->info('Schema file successfully generated.');
    }
}
