<?php declare(strict_types=1);

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
    protected bool $enable;

    protected ?string $store;

    protected ?int $ttl;

    protected bool $useFileCache;

    protected string $fileCachePath;

    public function __construct(
        protected ConfigRepository $configRepository,
        protected Filesystem $filesystem,
    ) {
        $config = $this->configRepository->get('lighthouse.query_cache');

        $this->enable = (bool) $config['enable'];
        $this->store = $config['store'] ?? null;
        $this->ttl = $config['ttl'] ?? null;
        $this->useFileCache = $config['use_file_cache'] ?? false;
        $this->fileCachePath = $config['file_cache_path'] ?? base_path('bootstrap/cache');
    }

    public function isEnabled(): bool
    {
        return $this->enable;
    }

    public function clearFileCache(?int $hours = null): void
    {
        $files = $this->filesystem->glob("{$this->fileCachePath}/query-*.php");

        if (is_int($hours)) {
            $threshold = now()->subHours($hours)->timestamp;
            $files = array_filter(
                $files,
                fn (string $file): bool => $this->filesystem->lastModified($file) < $threshold,
            );
        }

        $this->filesystem->delete($files);
    }

    /** @param  \Closure(): DocumentNode  $build */
    public function fromCacheOrBuild(string $hash, \Closure $build): DocumentNode
    {
        return $this->useFileCache
            ? $this->fromFileCacheOrBuild($hash, $build)
            : $this->fromCacheStoreOrBuild($hash, $build);
    }

    /** @param  \Closure(): DocumentNode  $build */
    protected function fromFileCacheOrBuild(string $hash, \Closure $build): DocumentNode
    {
        $filePath = "{$this->fileCachePath}/query-{$hash}.php";

        if ($this->filesystem->exists($filePath)) {
            $queryData = require $filePath;
            if (! is_array($queryData)) {
                throw new InvalidQueryCacheContentsException($filePath, $queryData);
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
        $this->filesystem->put(path: $filePath, contents: $contents, lock: true);

        return $query;
    }

    /** @param  \Closure(): DocumentNode  $build */
    protected function fromCacheStoreOrBuild(string $hash, \Closure $build): DocumentNode
    {
        $cacheFactory = Container::getInstance()->make(CacheFactory::class);
        $store = $cacheFactory->store($this->store);

        return $store->remember(key: "lighthouse:query:{$hash}", ttl: $this->ttl, callback: $build);
    }
}
