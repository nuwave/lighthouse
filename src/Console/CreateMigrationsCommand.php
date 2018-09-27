<?php

namespace Nuwave\Lighthouse\Console;

use Illuminate\Console\Command;
use GraphQL\Utils\SchemaPrinter;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class CreateMigrationsCommand extends Command
{
    /**
     * @var Filesystem
     */
    protected $origin;

    /**
     * @var Filesystem
     */
    protected $destination;

    /**
     * @var string
     */
    protected $filename = 'create_subscription_tables.php';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lighthouse:create-migrations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Store migration files locally.';

    /**
     * Create instance of migration command.
     */
    public function __construct()
    {
        parent::__construct();

        $this->origin = new Filesystem(
            new Local(realpath(__DIR__.'/../../database/migrations'))
        );

        $this->destination = new Filesystem(
            new Local(base_path('database/migrations'))
        );
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $prefix = date('Y_m_d_His');
        $path = "{$prefix}_{$this->filename}";

        $file = $this->origin->get($this->filename);

        $this->destination->put($path, $file->read());
        $this->info('Successfully copied migrations files to /database/migrations folder.');
    }
}
