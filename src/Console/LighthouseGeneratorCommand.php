<?php

namespace Nuwave\Lighthouse\Console;

use Illuminate\Console\GeneratorCommand;

abstract class LighthouseGeneratorCommand extends GeneratorCommand
{
    /**
     * Get the desired class name from the input.
     *
     * As a typical workflow would be to write the schema first and then copy-paste
     * a field name to generate a class for it, we uppercase it so the user does
     * not run into unnecessary errors. You're welcome.
     *
     * @return string
     */
    protected function getNameInput(): string
    {
        return ucfirst(trim($this->argument('name')));
    }
}
