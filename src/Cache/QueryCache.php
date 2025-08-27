<?php

namespace Nuwave\Lighthouse\Cache;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Utils\AST;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Filesystem\Filesystem;
use Nuwave\Lighthouse\Exceptions\InvalidQueryCacheContentsException;

class QueryCache
{
    private bool $enable;
    private ?string $store;
    private ?int $ttl;
    private bool $useFileCache;
    private string $fileCachePath;

    public function __construct(
        private ConfigRepository $configRepository,
        protected Filesystem $filesystem,
    ) {
        $config = $this->configRepository->get('lighthouse.query_cache');
        $this->enable = $config['enable'];
        $this->store = $config['store'];
        $this->ttl = $config['ttl'];

        $this->useFileCache = $config['use_file_cache'] ?? false;
        $path = $config['file_cache_path'] ?? base_path('bootstrap/cache');
        $this->fileCachePath = rtrim($path, '/') . '/';
    }

    public function isEnabled(): bool
    {
        return $this->enable;
    }

    public function fileCachePath(): string
    {
        return $this->fileCachePath;
    }

    public function clearFileCache(?int $hours = null): void
    {
        $files = $this->filesystem->glob($this->fileCachePath() . 'query-*.php');
        if (is_int($hours)) {
            $threshold = now()->subHours($hours)->timestamp;
            $files = array_filter(
                $files,
                fn(string $file) => $this->filesystem->lastModified($file) < $threshold
            );
        }
        $this->filesystem->delete($files);
    }

    /**
     * @param callable(): DocumentNode $build
     */
    public function fromCacheOrBuild(string $hash, callable $build): DocumentNode
    {
        if ($this->useFileCache) {
            return $this->fromFileCacheOrBuild($hash, $build);
        }

        $cacheFactory = Container::getInstance()->make(CacheFactory::class);
        $store = $cacheFactory->store($this->store);

        return $store->remember(
            "lighthouse:query:{$hash}",
            $this->ttl,
            $build,
        );
    }

    /**
     * @param callable(): DocumentNode $build
     */
    private function fromFileCacheOrBuild(string $hash, callable $build): DocumentNode
    {
        $filename = $this->fileCachePath() . 'query-' . $hash . '.php';
        if ($this->filesystem->exists($filename)) {
            $queryData = require $filename;
            if (!is_array($queryData)) {
                throw new InvalidQueryCacheContentsException($filename, $queryData);
            }
            $query = AST::fromArray($queryData);
            assert($query instanceof DocumentNode);

            return $query;
        }

        $query = $build();

        $variable = var_export(
            value: $query->toArray(),
            return: true,
        );
        $contents = /** @lang PHP */ "<?php return {$variable};";

        // To prevent opcache from picking up an incomplete file while it is written,
        // we write a temporary file first. Then, we move the temporary file
        // to the final location, which is an atomic operation.
        $partialPath = "{$filename}.partial";
        $this->filesystem->put(path: $partialPath, contents: $contents, lock: true);
        $this->filesystem->move(path: $partialPath, target: $filename);

        return $query;
    }
}
