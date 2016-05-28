<?php

namespace Nuwave\Lighthouse\Tests\Support\Console;

use Orchestra\Testbench\Console\Kernel;

class Kernel extends Kernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \Nuwave\Lighthouse\Support\Console\Commands\SchemaCommand::class,
    ];
}
