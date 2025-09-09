<?php declare(strict_types=1);

namespace Tests\Console;

use Carbon\Carbon;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Nuwave\Lighthouse\Console\ClearQueryCacheCommand;
use Tests\TestCase;

final class ClearQueryCacheCommandTest extends TestCase
{
    private const EMPTY_PHP_FILE_CONTENTS = '<?php';

    private FilesystemAdapter $filesystem;

    protected function setUp(): void
    {
        parent::setUp();

        $filesystem = Storage::fake();
        assert($filesystem instanceof FilesystemAdapter);
        $this->filesystem = $filesystem;

        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.query_cache.enable', true);
        $config->set('lighthouse.query_cache.use_file_cache', true);
        $config->set('lighthouse.query_cache.opcache_path', $this->filesystem->path(''));
    }

    public function testDeleteAll(): void
    {
        $this->filesystem->put('lighthouse-query-1.php', self::EMPTY_PHP_FILE_CONTENTS);
        $this->filesystem->put('lighthouse-query-2.php', self::EMPTY_PHP_FILE_CONTENTS);
        $this->filesystem->put('unrelated.php', self::EMPTY_PHP_FILE_CONTENTS);

        $this->commandTester(new ClearQueryCacheCommand())
            ->execute([]);

        $this->filesystem->assertMissing('lighthouse-query-1.php');
        $this->filesystem->assertMissing('lighthouse-query-2.php');
        $this->filesystem->assertExists('unrelated.php');
    }

    public function testDeleteThreshold(): void
    {
        $this->travelTo(Carbon::createStrict(2024, 6, 1, 10, 0, 0));
        $this->putWithCurrentTimestamp('lighthouse-query-very-old.php');

        $this->travelTo(Carbon::createStrict(2025, 6, 1, 9, 59, 59));
        $this->putWithCurrentTimestamp('lighthouse-query-just-too-old.php');

        $this->travelTo(Carbon::createStrict(2025, 6, 1, 10, 0, 0));
        $this->putWithCurrentTimestamp('lighthouse-query-just-still-valid.php');

        $this->travelTo(Carbon::createStrict(2025, 6, 1, 12, 0, 0));
        $this->putWithCurrentTimestamp('lighthouse-query-very-recent.php');
        $this->putWithCurrentTimestamp('unrelated.php');

        $this->commandTester(new ClearQueryCacheCommand())
            ->execute(['--hours' => 2]);
        $this->filesystem->assertMissing('lighthouse-query-very-old.php');
        $this->filesystem->assertMissing('lighthouse-query-just-too-old.php');
        $this->filesystem->assertExists('lighthouse-query-just-still-valid.php');
        $this->filesystem->assertExists('lighthouse-query-very-recent.php');
        $this->filesystem->assertExists('unrelated.php');
    }

    private function putWithCurrentTimestamp(string $filename): void
    {
        $this->filesystem->put($filename, self::EMPTY_PHP_FILE_CONTENTS);

        $currentTimestamp = now()->timestamp;
        assert(is_int($currentTimestamp));

        \Safe\touch($this->filesystem->path($filename), $currentTimestamp);
    }
}
