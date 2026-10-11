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
            $astArray = require $path;
            assert(is_array($astArray), "The schema cache file at {$path} is expected to return an array.");

            /** @var SerializableDocumentAST $astArray */

            return DocumentAST::fromArray($astArray);
        }

        $documentAST = $build();
        $this->set($documentAST);

        return $documentAST;
    }
}
