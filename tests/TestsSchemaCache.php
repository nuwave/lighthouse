<?php declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;

trait TestsSchemaCache
{
    protected function setUpSchemaCache(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $config->set('lighthouse.schema_cache.enable', true);
        $config->set('lighthouse.schema_cache.path', $this->schemaCachePath());
    }

    protected function schemaCachePath(): string
    {
        return __DIR__ . '/storage/lighthouse-schema.php';
    }

    protected function tearDownSchemaCache(): void
    {
        $filesystem = $this->app->make(Filesystem::class);
        $filesystem->delete($this->schemaCachePath());
    }
}
