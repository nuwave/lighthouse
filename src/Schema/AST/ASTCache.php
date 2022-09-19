<?php

namespace Nuwave\Lighthouse\Schema\AST;

use Closure;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use Nuwave\Lighthouse\Exceptions\InvalidSchemaCacheContentsException;
use Nuwave\Lighthouse\Exceptions\UnknownCacheVersionException;

/**
 * @phpstan-type CacheConfig array{
 *   enable: bool,
 *   version: 1|2|null,
 *   store: string|null,
 *   key: string,
 *   ttl: int|null,
 *   path: string|null,
 * }
 *
 * @phpstan-import-type SerializableDocumentAST from DocumentAST
 */
class ASTCache
{
    /**
     * @var bool
     */
    protected $enable;

    /**
     * @var 1|2
     */
    protected $version;

    /**
     * @var string|null
     */
    protected $store;

    /**
     * @var string
     */
    protected $key;

    /**
     * @var int|null
     */
    protected $ttl;

    /**
     * @var string
     */
    protected $path;

    public function __construct(ConfigRepository $config)
    {
        /** @var CacheConfig $cacheConfig */
        $cacheConfig = $config->get('lighthouse.cache');

        $this->enable = $cacheConfig['enable'];

        $version = $cacheConfig['version'] ?? 1;

        switch ($version) {
            case 1:
                $this->store = $cacheConfig['store'] ?? null;
                $this->key = $cacheConfig['key'];
                $this->ttl = $cacheConfig['ttl'];
                break;
            case 2:
                $this->path = $cacheConfig['path'] ?? base_path('bootstrap/cache/lighthouse-schema.php');
                break;
            default:
                throw new UnknownCacheVersionException($version);
        }

        $this->version = (int) $version;
    }

    public function isEnabled(): bool
    {
        return $this->enable;
    }

    public function set(DocumentAST $documentAST): void
    {
        if (1 === $this->version) {
            $this->store()->set($this->key, $documentAST, $this->ttl);

            return;
        }

        $variable = var_export($documentAST->toArray(), true);
        $this->filesystem()->put($this->path, /** @lang PHP */ "<?php return {$variable};");
    }

    public function clear(): void
    {
        if (1 === $this->version) {
            $this->store()->forget($this->key);

            return;
        }

        $this->filesystem()->delete($this->path);
    }

    /**
     * @param \Closure(): DocumentAST $build
     */
    public function fromCacheOrBuild(Closure $build): DocumentAST
    {
        if (1 === $this->version) {
            return $this->store()->remember(
                $this->key,
                $this->ttl,
                $build
            );
        }

        if ($this->filesystem()->exists($this->path)) {
            $ast = require $this->path;
            if (! is_array($ast)) {
                throw new InvalidSchemaCacheContentsException($this->path, $ast);
            }
            /** @var SerializableDocumentAST $ast */

            return DocumentAST::fromArray($ast);
        }

        $documentAST = $build();
        $this->set($documentAST);

        return $documentAST;
    }

    protected function store(): CacheRepository
    {
        /** @var \Illuminate\Contracts\Cache\Factory $cacheFactory */
        $cacheFactory = app(CacheFactory::class);

        return $cacheFactory->store($this->store);
    }

    protected function filesystem(): Filesystem
    {
        return app(Filesystem::class);
    }
}
