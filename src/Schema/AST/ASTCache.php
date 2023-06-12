<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\AST;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use Nuwave\Lighthouse\Exceptions\InvalidSchemaCacheContentsException;

/**
 * @phpstan-type CacheConfig array{
 *   enable: bool,
 *   path: string|null,
 * }
 *
 * @phpstan-import-type SerializableDocumentAST from DocumentAST
 */
class ASTCache
{
    protected bool $enable;

    protected string $path;

    public function __construct(ConfigRepository $config)
    {
        /** @var CacheConfig $cacheConfig */
        $cacheConfig = $config->get('lighthouse.schema_cache');
        $this->enable = (bool) $cacheConfig['enable'];
        $this->path = $cacheConfig['path'] ?? base_path('bootstrap/cache/lighthouse-schema.php');
    }

    public function isEnabled(): bool
    {
        return $this->enable;
    }

    public function set(DocumentAST $documentAST): void
    {
        $variable = var_export($documentAST->toArray(), true);
        $this->filesystem()->put($this->path, /** @lang PHP */ "<?php return {$variable};");
    }

    public function clear(): void
    {
        $this->filesystem()->delete($this->path);
    }

    /** @param  callable(): DocumentAST  $build */
    public function fromCacheOrBuild(callable $build): DocumentAST
    {
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

    protected function filesystem(): Filesystem
    {
        return Container::getInstance()->make(Filesystem::class);
    }
}
