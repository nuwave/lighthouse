<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\AST;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use Nuwave\Lighthouse\Exceptions\InvalidSchemaCacheContentsException;

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

        // Since the schema cache can be very large, we write it to a temporary file first.
        // This avoids issues with the filesystem not being able to write large files atomically.
        // Then, we move the temporary file to the final location which is an atomic operation.
        $path = $this->path();
        $partialPath = "{$path}.partial";
        $this->filesystem->put(path: $partialPath, contents: $contents, lock: true);
        $this->filesystem->move(path: $partialPath, target: $path);
    }

    public function clear(): void
    {
        $this->filesystem->delete($this->path());
    }

    /** @param  callable(): DocumentAST  $build */
    public function fromCacheOrBuild(callable $build): DocumentAST
    {
        $path = $this->path();
        if ($this->filesystem->exists($path)) {
            $ast = require $path;
            if (! is_array($ast)) {
                throw new InvalidSchemaCacheContentsException($path, $ast);
            }

            /** @var SerializableDocumentAST $ast */

            return DocumentAST::fromArray($ast);
        }

        $documentAST = $build();
        $this->set($documentAST);

        return $documentAST;
    }
}
