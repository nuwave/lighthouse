<?php

namespace Tests;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;

trait TestsSchemaCache
{
    protected function setUpSchemaCache(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        assert($config instanceof ConfigRepository);

        $config->set('lighthouse.cache.enable', true);
        $config->set('lighthouse.cache.path', $this->schemaCachePath());
    }

    protected function schemaCachePath(): string
    {
        return __DIR__ . '/storage/lighthouse-schema.php';
    }

    protected function tearDownSchemaCache(): void
    {
        $filesystem = $this->app->make(Filesystem::class);
        assert($filesystem instanceof Filesystem);

        $filesystem->delete($this->schemaCachePath());
    }

    /**
     * Data provider for the different cache versions.
     *
     * @return array<int, array{int}>
     */
    public function cacheVersions(): array
    {
        return [
            [1],
            [2],
        ];
    }
}
