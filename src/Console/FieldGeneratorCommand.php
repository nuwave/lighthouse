<?php

namespace Nuwave\Lighthouse\Console;

use Symfony\Component\Console\Input\InputOption;

abstract class FieldGeneratorCommand extends LighthouseGeneratorCommand
{
    protected function getStub(): string
    {
        $stub = $this->option('full')
            ? 'field_full'
            : 'field_simple';

        return __DIR__ . "/stubs/{$stub}.stub";
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions(): array
    {
        return [
            ['full', 'F', InputOption::VALUE_NONE, 'Include the seldom needed resolver arguments $context and $resolveInfo'],
        ];
    }
}
