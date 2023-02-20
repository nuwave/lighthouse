<?php

namespace Nuwave\Lighthouse\Testing;

use Safe\Exceptions\FilesystemException;

/**
 * Refreshes the schema cache once before any tests are run.
 *
 * @mixin \Illuminate\Foundation\Testing\Concerns\InteractsWithConsole
 */
trait RefreshesSchemaCache
{
    /**
     * Marks that the schema cache was refreshed.
     *
     * Static variables persist during the execution of a single process.
     * In parallel testing each process has a separate instance of this class.
     *
     * @var bool
     */
    protected static $schemaCacheWasRefreshed = false;

    /**
     * Path to the file used for coordinating exactly-one semantics.
     *
     * @var string
     */
    protected static $lockFilePath = __DIR__ . '/schema-cache-refreshing';

    protected function bootRefreshesSchemaCache(): void
    {
        if (! static::$schemaCacheWasRefreshed) {
            // We utilize the filesystem as shared mutable state to coordinate between processes,
            // since the tests might be run in parallel, and we want to ensure the schema cache
            // is refreshed exactly once before all tests.
            \Safe\touch(self::$lockFilePath);
            $lockFile = \Safe\fopen(self::$lockFilePath, 'r');

            // Attempt to get an exclusive lock - first process wins
            try {
                \Safe\flock($lockFile, LOCK_EX | LOCK_NB);

                // Since we are the single process that has an exclusive lock, we have to write the cache
                $this->artisan('lighthouse:cache');
            } catch (FilesystemException $e) {
                // If no exclusive lock is available, block until the first process is done and wrote the cache
                \Safe\flock($lockFile, LOCK_SH);
            }

            self::$schemaCacheWasRefreshed = true;
        }
    }
}
