<?php

namespace Nuwave\Lighthouse\Console;

use Illuminate\Console\GeneratorCommand;

/**
 * This class can be removed if/when https://github.com/laravel/framework/pull/26176 is merged.
 */
abstract class LighthouseGeneratorCommand extends GeneratorCommand
{
    /**
     * Get the desired class name from the input.
     *
     * @return string
     */
    protected function getNameInput()
    {
        return ucfirst(trim($this->argument('name')));
    }
}
