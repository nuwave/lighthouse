<?php

namespace Nuwave\Relay\Tests\Support\Console;

use Orchestra\Testbench\Console\Kernel;

class Kernel extends Kernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \Nuwave\Relay\Support\Console\Commands\SchemaCommand::class,
    ];
}
