<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Cache;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Utils\AST;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Filesystem\Filesystem;
use Nuwave\Lighthouse\Support\Utils;

class QueryCache
{
    protected bool $enable;

    protected string $mode;

    protected string $opcachePath;

    protected ?string $store;

    protected ?int $ttl;

    public function __construct(
        protected ConfigRepository $configRepository,
        protected Filesystem $filesystem,
    ) {
        $config = $this->configRepository->get('lighthouse.query_cache');

        $this->enable = (bool) $config['enable'];
        $this->mode = $config['mode'] ?? 'store';
        $this->opcachePath = rtrim($config['opcache_path'] ?? base_path('bootstrap/cache'), '/');
        $this->store = $config['store'] ?? null;
        $this->ttl = $config['ttl'] ?? null;
    }

    public function isEnabled(): bool
    {
        return $this->enable;
    }

    public function clear(?int $opcacheTTLHours, bool $opcacheOnly): void
    {
        if (in_array($this->mode, ['store', 'hybrid'], strict: true)
            && ! $opcacheOnly
        ) {
            $store = $this->makeCacheStore();
            $store->clear();
        }

        if (in_array($this->mode, ['opcache', 'hybrid'], strict: true)) {
            $files = $this->filesystem->glob($this->opcacheFilePath('*'));

            if (is_int($opcacheTTLHours)) {
                $threshold = now()->subHours($opcacheTTLHours)->timestamp;
                $files = array_filter(
                    $files,
                    fn (string $file): bool => $this->filesystem->lastModified($file) < $threshold,
                );
            }

            $this->filesystem->delete($files);
        }
    }

    /** @param  \Closure(): DocumentNode  $parse */
    public function fromCacheOrParse(string $hash, \Closure $parse): DocumentNode
    {
        return match ($this->mode) {
            'store' => $this->fromStoreOrParse($hash, $parse),
            'opcache' => $this->fromOPcacheOrParse($hash, $parse),
            'hybrid' => $this->fromHybridOrParse($hash, $parse),
            default => throw new \InvalidArgumentException("Invalid query cache mode: {$this->mode}."),
        };
    }

    /** @param  \Closure(): DocumentNode  $parse */
    protected function fromStoreOrParse(string $hash, \Closure $parse): DocumentNode
    {
        $store = $this->makeCacheStore();
        $key = "lighthouse:query:{$hash}";

        // The AST is cached as an array rather than as a DocumentNode instance.
        // Cache stores serialize objects, and applications may restrict which classes
        // unserialize() accepts, which would turn the AST into __PHP_Incomplete_Class.
        // Anything else found under this key predates that and is simply reparsed.
        $astArray = $store->get(key: $key);
        if (is_array($astArray)) {
            $astInstance = AST::fromArray($astArray);
            assert($astInstance instanceof DocumentNode, 'The cached AST array is expected to convert to a DocumentNode.');

            return $astInstance;
        }

        $query = $parse();
        $store->put(key: $key, value: $query->toArray(), ttl: $this->ttl);

        return $query;
    }

    /** @param  \Closure(): DocumentNode  $parse */
    protected function fromOPcacheOrParse(string $hash, \Closure $parse): DocumentNode
    {
        $path = $this->opcacheFilePath($hash);

        if ($this->filesystem->exists($path)) {
            return $this->requireOPcacheFile($path);
        }

        $query = $parse();

        $contents = static::opcacheFileContents($query);
        Utils::atomicPut(filesystem: $this->filesystem, path: $path, contents: $contents);

        return $query;
    }

    /** @param  \Closure(): DocumentNode  $parse */
    protected function fromHybridOrParse(string $hash, \Closure $parse): DocumentNode
    {
        $path = $this->opcacheFilePath($hash);

        if ($this->filesystem->exists($path)) {
            return $this->requireOPcacheFile($path);
        }

        $store = $this->makeCacheStore();

        $contents = $store->get(key: "lighthouse:query:{$hash}");
        if (is_string($contents)) {
            Utils::atomicPut(filesystem: $this->filesystem, path: $path, contents: $contents);

            return $this->requireOPcacheFile($path);
        }

        $query = $parse();

        $contents = static::opcacheFileContents($query);
        $store->put(key: "lighthouse:query:{$hash}", value: $contents, ttl: $this->ttl);
        Utils::atomicPut(filesystem: $this->filesystem, path: $path, contents: $contents);

        return $query;
    }

    protected function makeCacheStore(): CacheRepository
    {
        $cacheFactory = Container::getInstance()->make(CacheFactory::class);

        return $cacheFactory->store($this->store);
    }

    public static function opcacheFileContents(DocumentNode $query): string
    {
        $queryArrayString = var_export(
            value: $query->toArray(),
            return: true,
        );

        return "<?php return {$queryArrayString};";
    }

    protected function requireOPcacheFile(string $path): DocumentNode
    {
        $astArray = require $path;
        assert(is_array($astArray), "The query cache file at {$path} is expected to return an array.");

        $astInstance = AST::fromArray($astArray);
        assert($astInstance instanceof DocumentNode, 'The AST array is expected to convert to a DocumentNode.');

        return $astInstance;
    }

    protected function opcacheFilePath(string $hash): string
    {
        return "{$this->opcachePath}/lighthouse-query-{$hash}.php";
    }
}
