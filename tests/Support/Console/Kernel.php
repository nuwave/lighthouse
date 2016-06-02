<?php

namespace Nuwave\Lighthouse\Tests\Support\Console;

use Orchestra\Testbench\Console\Kernel as BaseKernel;

class Kernel extends BaseKernel
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
