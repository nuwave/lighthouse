<?php

namespace Tests\Console;

use Carbon\Carbon;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use Nuwave\Lighthouse\Console\ClearQueryCacheCommand;
use Tests\TestCase;

class ClearQueryCacheCommandTest extends TestCase
{
    const STORAGE_DIR = __DIR__ . '/../storage';

    protected function setUp(): void
    {
        parent::setUp();

        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.query_cache.enable', true);
        $config->set('lighthouse.query_cache.use_file_cache', true);
        $config->set('lighthouse.query_cache.file_cache_path', self::STORAGE_DIR);

        $filesystem = $this->app->make(Filesystem::class);
        $filesystem->put(self::STORAGE_DIR . '/query-1.php', '<?php');
        $filesystem->put(self::STORAGE_DIR . '/query-2.php', '<?php');
        $filesystem->put(self::STORAGE_DIR . '/unrelated.php', '<?php');
    }

    protected function tearDown(): void
    {
        $filesystem = $this->app->make(Filesystem::class);
        // remove all files except dotfiles
        $filesystem->delete(
            $filesystem->files(self::STORAGE_DIR)
        );

        parent::tearDown();
    }

    public function testDeleteAll()
    {
        $this->commandTester(new ClearQueryCacheCommand())->execute([]);

        $this->assertFileDoesNotExist(self::STORAGE_DIR . '/query-1.php');
        $this->assertFileDoesNotExist(self::STORAGE_DIR . '/query-2.php');
        $this->assertFileExists(self::STORAGE_DIR . '/unrelated.php');
    }

    public function testDeleteThreshold()
    {
        $this->travelTo('2025-06-01 12:00:00');
        \Safe\touch(self::STORAGE_DIR . '/query-1.php', Carbon::parse('2025-06-01 10:00:00')->timestamp);
        \Safe\touch(self::STORAGE_DIR . '/query-2.php', Carbon::parse('2025-06-01 09:59:59')->timestamp);

        $this->commandTester(new ClearQueryCacheCommand())->execute(['--hours' => 2]);
        $this->assertFileExists(self::STORAGE_DIR . '/query-1.php');
        $this->assertFileDoesNotExist(self::STORAGE_DIR . '/query-2.php');
        $this->assertFileExists(self::STORAGE_DIR . '/unrelated.php');
    }
}
