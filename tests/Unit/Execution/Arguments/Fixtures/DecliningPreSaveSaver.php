<?php declare(strict_types=1);

namespace Tests\Unit\Execution\Arguments\Fixtures;

use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;
use Nuwave\Lighthouse\Support\Contracts\PreSaveArgumentsAware;

final class DecliningPreSaveSaver implements ArgResolver, PreSaveArgumentsAware
{
    public bool $wasOfferedPreSaveArguments = false;

    public ?ArgumentSet $receivedArgs = null;

    public function withPreSaveArguments(array $arguments): ?static
    {
        $this->wasOfferedPreSaveArguments = true;

        return null;
    }

    /** @param  ArgumentSet  $args */
    public function __invoke(mixed $root, $args): mixed
    {
        $this->receivedArgs = $args;

        return $root;
    }
}
