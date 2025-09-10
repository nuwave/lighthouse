<?php declare(strict_types=1);

namespace Tests\Console;

use Carbon\Carbon;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Nuwave\Lighthouse\Console\ClearQueryCacheCommand;
use Tests\TestCase;

final class ClearQueryCacheCommandTest extends TestCase
{
    private const MINIMAL_PHP_FILE = '<?php';

    public function testClearsOnlyLaravelCacheStoreInStoreMode(): void
    {
        $filesystem = $this->configureQueryCacheMode('store');

        $lighthouseQueryFile1 = 'lighthouse-query-1.php';
        $lighthouseQueryFile2 = 'lighthouse-query-2.php';
        $unrelatedFile = 'unrelated.php';
        $filesystem->put($lighthouseQueryFile1, self::MINIMAL_PHP_FILE);
        $filesystem->put($lighthouseQueryFile2, self::MINIMAL_PHP_FILE);
        $filesystem->put($unrelatedFile, self::MINIMAL_PHP_FILE);

        $lighthouseCacheKey1 = 'lighthouse:query:hash1';
        $lighthouseCacheKey2 = 'lighthouse:query:hash2';
        $unrelatedCacheKey = 'unrelated:cache';
        Cache::put($lighthouseCacheKey1, 'query1');
        Cache::put($lighthouseCacheKey2, 'query2');
        Cache::put($unrelatedCacheKey, 'data');

        $this->commandTester(new ClearQueryCacheCommand())->execute([]);

        $filesystem->assertExists($lighthouseQueryFile1);
        $filesystem->assertExists($lighthouseQueryFile2);
        $filesystem->assertExists($unrelatedFile);

        $this->assertFalse(Cache::has($lighthouseCacheKey1), 'Lighthouse cache entries should be cleared in store mode');
        $this->assertFalse(Cache::has($lighthouseCacheKey2), 'Lighthouse cache entries should be cleared in store mode');
        $this->assertFalse(Cache::has($unrelatedCacheKey), 'All cache entries are cleared when store is cleared');
    }

    public function testClearsOnlyOPcacheFilesInOPcacheMode(): void
    {
        $filesystem = $this->configureQueryCacheMode('opcache');

        $lighthouseQueryFile1 = 'lighthouse-query-1.php';
        $lighthouseQueryFile2 = 'lighthouse-query-2.php';
        $unrelatedFile = 'unrelated.php';
        $filesystem->put($lighthouseQueryFile1, self::MINIMAL_PHP_FILE);
        $filesystem->put($lighthouseQueryFile2, self::MINIMAL_PHP_FILE);
        $filesystem->put($unrelatedFile, self::MINIMAL_PHP_FILE);

        $lighthouseCacheKey1 = 'lighthouse:query:hash1';
        $lighthouseCacheKey2 = 'lighthouse:query:hash2';
        Cache::put($lighthouseCacheKey1, 'query1');
        Cache::put($lighthouseCacheKey2, 'query2');

        $this->commandTester(new ClearQueryCacheCommand())->execute([]);

        $filesystem->assertMissing($lighthouseQueryFile1);
        $filesystem->assertMissing($lighthouseQueryFile2);
        $filesystem->assertExists($unrelatedFile);

        $this->assertTrue(Cache::has($lighthouseCacheKey1), 'Cache store should remain untouched in opcache mode');
        $this->assertTrue(Cache::has($lighthouseCacheKey2), 'Cache store should remain untouched in opcache mode');
    }

    public function testClearsBothOPcacheFilesAndCacheStoreInHybridMode(): void
    {
        $filesystem = $this->configureQueryCacheMode('hybrid');

        $lighthouseQueryFile1 = 'lighthouse-query-1.php';
        $lighthouseQueryFile2 = 'lighthouse-query-2.php';
        $unrelatedFile = 'unrelated.php';
        $filesystem->put($lighthouseQueryFile1, self::MINIMAL_PHP_FILE);
        $filesystem->put($lighthouseQueryFile2, self::MINIMAL_PHP_FILE);
        $filesystem->put($unrelatedFile, self::MINIMAL_PHP_FILE);

        $lighthouseCacheKey1 = 'lighthouse:query:hash1';
        $lighthouseCacheKey2 = 'lighthouse:query:hash2';
        Cache::put($lighthouseCacheKey1, 'query1');
        Cache::put($lighthouseCacheKey2, 'query2');

        $this->commandTester(new ClearQueryCacheCommand())->execute([]);

        $filesystem->assertMissing($lighthouseQueryFile1);
        $filesystem->assertMissing($lighthouseQueryFile2);
        $filesystem->assertExists($unrelatedFile);

        $this->assertFalse(Cache::has($lighthouseCacheKey1), 'Cache store should be cleared in hybrid mode');
        $this->assertFalse(Cache::has($lighthouseCacheKey2), 'Cache store should be cleared in hybrid mode');
    }

    public function testOPcacheTTLOptionDeletesOnlyFilesOlderThanSpecifiedHours(): void
    {
        $filesystem = $this->configureQueryCacheMode('opcache');

        $this->travelTo(Carbon::createStrict(2024, 6, 1, 10, 0, 0));
        $veryOldFile = 'lighthouse-query-very-old.php';
        $this->createFileWithCurrentTimestamp($filesystem, $veryOldFile);

        $this->travelTo(Carbon::createStrict(2025, 6, 1, 9, 59, 59));
        $justTooOldFile = 'lighthouse-query-just-too-old.php';
        $this->createFileWithCurrentTimestamp($filesystem, $justTooOldFile);

        $this->travelTo(Carbon::createStrict(2025, 6, 1, 10, 0, 0));
        $justStillValidFile = 'lighthouse-query-just-still-valid.php';
        $this->createFileWithCurrentTimestamp($filesystem, $justStillValidFile);

        $this->travelTo(Carbon::createStrict(2025, 6, 1, 12, 0, 0));
        $veryRecentFile = 'lighthouse-query-very-recent.php';
        $unrelatedFile = 'unrelated.php';
        $this->createFileWithCurrentTimestamp($filesystem, $veryRecentFile);
        $this->createFileWithCurrentTimestamp($filesystem, $unrelatedFile);

        $ttlHours = 2;
        $this->commandTester(new ClearQueryCacheCommand())
            ->execute(['--opcache-ttl-hours' => $ttlHours]);

        $filesystem->assertMissing($veryOldFile);
        $filesystem->assertMissing($justTooOldFile);
        $filesystem->assertExists($justStillValidFile);
        $filesystem->assertExists($veryRecentFile);
        $filesystem->assertExists($unrelatedFile);
    }

    public function testOPcacheTTLInHybridModeDeletesOldFilesButClearsEntireCacheStore(): void
    {
        $filesystem = $this->configureQueryCacheMode('hybrid');

        $lighthouseCacheKey1 = 'lighthouse:query:hash1';
        $lighthouseCacheKey2 = 'lighthouse:query:hash2';
        Cache::put($lighthouseCacheKey1, 'query1');
        Cache::put($lighthouseCacheKey2, 'query2');

        $this->travelTo(Carbon::createStrict(2025, 6, 1, 10, 0, 0));
        $oldFile = 'lighthouse-query-old.php';
        $this->createFileWithCurrentTimestamp($filesystem, $oldFile);

        $this->travelTo(Carbon::createStrict(2025, 6, 1, 12, 0, 0));
        $recentFile = 'lighthouse-query-recent.php';
        $this->createFileWithCurrentTimestamp($filesystem, $recentFile);

        $ttlHours = 1;
        $this->commandTester(new ClearQueryCacheCommand())
            ->execute(['--opcache-ttl-hours' => $ttlHours]);

        $filesystem->assertMissing($oldFile);
        $filesystem->assertExists($recentFile);

        $this->assertFalse(Cache::has($lighthouseCacheKey1), 'Cache store should be completely cleared even with TTL in hybrid mode');
        $this->assertFalse(Cache::has($lighthouseCacheKey2), 'Cache store should be completely cleared even with TTL in hybrid mode');
    }

    public function testOPcacheOnlyOptionInStoreModeDoesNothing(): void
    {
        $filesystem = $this->configureQueryCacheMode('store');

        $lighthouseQueryFile1 = 'lighthouse-query-1.php';
        $lighthouseQueryFile2 = 'lighthouse-query-2.php';
        $filesystem->put($lighthouseQueryFile1, self::MINIMAL_PHP_FILE);
        $filesystem->put($lighthouseQueryFile2, self::MINIMAL_PHP_FILE);

        $lighthouseCacheKey1 = 'lighthouse:query:hash1';
        $lighthouseCacheKey2 = 'lighthouse:query:hash2';
        Cache::put($lighthouseCacheKey1, 'query1');
        Cache::put($lighthouseCacheKey2, 'query2');

        $this->commandTester(new ClearQueryCacheCommand())
            ->execute(['--opcache-only' => true]);

        $filesystem->assertExists($lighthouseQueryFile1);
        $filesystem->assertExists($lighthouseQueryFile2);

        $this->assertTrue(Cache::has($lighthouseCacheKey1), 'Cache store should remain untouched when using opcache-only in store mode');
        $this->assertTrue(Cache::has($lighthouseCacheKey2), 'Cache store should remain untouched when using opcache-only in store mode');
    }

    public function testOPcacheOnlyOptionInHybridModeClearsOnlyOPcacheFiles(): void
    {
        $filesystem = $this->configureQueryCacheMode('hybrid');

        $lighthouseQueryFile1 = 'lighthouse-query-1.php';
        $lighthouseQueryFile2 = 'lighthouse-query-2.php';
        $filesystem->put($lighthouseQueryFile1, self::MINIMAL_PHP_FILE);
        $filesystem->put($lighthouseQueryFile2, self::MINIMAL_PHP_FILE);

        $lighthouseCacheKey1 = 'lighthouse:query:hash1';
        $lighthouseCacheKey2 = 'lighthouse:query:hash2';
        Cache::put($lighthouseCacheKey1, 'query1');
        Cache::put($lighthouseCacheKey2, 'query2');

        $this->commandTester(new ClearQueryCacheCommand())
            ->execute(['--opcache-only' => true]);

        $filesystem->assertMissing($lighthouseQueryFile1);
        $filesystem->assertMissing($lighthouseQueryFile2);

        $this->assertTrue(Cache::has($lighthouseCacheKey1), 'Cache store should remain untouched when using opcache-only option');
        $this->assertTrue(Cache::has($lighthouseCacheKey2), 'Cache store should remain untouched when using opcache-only option');
    }

    public function testCombiningOPcacheOnlyWithTTLDeletesOnlyOldOPcacheFiles(): void
    {
        $filesystem = $this->configureQueryCacheMode('opcache');

        $this->travelTo(Carbon::createStrict(2025, 6, 1, 10, 0, 0));
        $oldFile = 'lighthouse-query-old.php';
        $this->createFileWithCurrentTimestamp($filesystem, $oldFile);

        $this->travelTo(Carbon::createStrict(2025, 6, 1, 12, 0, 0));
        $recentFile = 'lighthouse-query-recent.php';
        $this->createFileWithCurrentTimestamp($filesystem, $recentFile);

        $ttlHours = 1;
        $this->commandTester(new ClearQueryCacheCommand())
            ->execute([
                '--opcache-only' => true,
                '--opcache-ttl-hours' => $ttlHours,
            ]);

        $filesystem->assertMissing($oldFile);
        $filesystem->assertExists($recentFile);
    }

    private function configureQueryCacheMode(string $mode): FilesystemAdapter
    {
        $filesystem = Storage::fake();
        assert($filesystem instanceof FilesystemAdapter);

        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.query_cache.enable', true);
        $config->set('lighthouse.query_cache.mode', $mode);
        $config->set('lighthouse.query_cache.opcache_path', $filesystem->path(''));

        return $filesystem;
    }

    private function createFileWithCurrentTimestamp(FilesystemAdapter $filesystem, string $filename): void
    {
        $filesystem->put($filename, self::MINIMAL_PHP_FILE);

        $currentTimestamp = now()->timestamp;
        assert(is_int($currentTimestamp));

        \Safe\touch($filesystem->path($filename), $currentTimestamp);
    }
}
