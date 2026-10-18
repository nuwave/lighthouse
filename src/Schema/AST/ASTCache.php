<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\AST;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use Nuwave\Lighthouse\Support\Utils;

/**
 * @phpstan-type CacheConfig array{
 *   enable: bool|int,
 *   path?: ?string,
 * }
 *
 * @phpstan-import-type SerializableDocumentAST from DocumentAST
 */
class ASTCache
{
    /**
     * Schema cache file contents, kept for the lifetime of the PHP process.
     *
     * Long-running workers such as Laravel Octane resolve a fresh ASTBuilder for each request.
     * Requiring the cache file each time would compile it and allocate the whole array again
     * when OPcache is not enabled for the worker, see https://github.com/nuwave/lighthouse/issues/2436.
     *
     * @var array<string, array{fingerprint: string, astArray: array<mixed>}>
     */
    protected static array $loaded = [];

    protected bool $enable;

    protected string $path;

    public function __construct(
        ConfigRepository $config,
        protected Filesystem $filesystem,
    ) {
        /** @var CacheConfig $cacheConfig */
        $cacheConfig = $config->get('lighthouse.schema_cache');
        $this->enable = (bool) $cacheConfig['enable'];
        $this->path = $cacheConfig['path'] ?? base_path('bootstrap/cache/lighthouse-schema.php');
    }

    public function isEnabled(): bool
    {
        return $this->enable;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function set(DocumentAST $documentAST): void
    {
        $variable = var_export(
            value: $documentAST->toArray(),
            return: true,
        );
        $contents = /** @lang PHP */ "<?php return {$variable};";

        Utils::atomicPut(filesystem: $this->filesystem, path: $this->path(), contents: $contents);
        unset(static::$loaded[$this->path()]);
    }

    public function clear(): void
    {
        $this->filesystem->delete($this->path());
        unset(static::$loaded[$this->path()]);
    }

    /** Forget all schema cache file contents kept in memory, the files on disk are not affected. */
    public static function forgetLoaded(): void
    {
        static::$loaded = [];
    }

    /** @param  callable(): DocumentAST  $build */
    public function fromCacheOrBuild(callable $build): DocumentAST
    {
        $astArray = $this->load($this->path());
        if ($astArray !== null) {
            /** @var SerializableDocumentAST $astArray */

            return DocumentAST::fromArray($astArray);
        }

        $documentAST = $build();
        $this->set($documentAST);

        return $documentAST;
    }

    /**
     * Load the cache file contents, reusing them from memory while the file is unchanged.
     *
     * @return array<mixed>|null
     */
    protected function load(string $path): ?array
    {
        $fingerprint = $this->fingerprint($path);
        if ($fingerprint === null) {
            unset(static::$loaded[$path]);

            return null;
        }

        $loaded = static::$loaded[$path] ?? null;
        if ($loaded !== null && $loaded['fingerprint'] === $fingerprint) {
            return $loaded['astArray'];
        }

        $astArray = Utils::requireArrayOrForget($this->filesystem, $path);
        if ($astArray === null) {
            unset(static::$loaded[$path]);

            return null;
        }

        static::$loaded[$path] = [
            'fingerprint' => $fingerprint,
            'astArray' => $astArray,
        ];

        return $astArray;
    }

    /** Identifies a version of the cache file without reading it, null if it does not exist. */
    protected function fingerprint(string $path): ?string
    {
        clearstatcache(true, $path);

        // Suppress the warning when the file does not exist, null is returned for that case
        $stat = @stat($path);
        if ($stat === false) {
            return null;
        }

        // Files are replaced atomically by moving a new file into place, which changes the inode
        return "{$stat['ino']}-{$stat['mtime']}-{$stat['size']}";
    }
}
