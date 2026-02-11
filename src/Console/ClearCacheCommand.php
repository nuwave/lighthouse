<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Console;

/**
 * @deprecated in favor of lighthouse:clear-schema-cache
 */
class ClearCacheCommand extends ClearSchemaCacheCommand
{
    protected $name = 'lighthouse:clear-cache';
}
