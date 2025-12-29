<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution;

use Composer\InstalledVersions;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Type\Schema;
use GraphQL\Validator\ValidationCache;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

class LighthouseValidationCache implements ValidationCache
{
    public function __construct(
        protected CacheRepository $cache,
        protected string $schemaHash,
        protected string $queryHash,
        protected string $rulesConfigHash,
        protected int $ttl,
    ) {}

    public function isValidated(Schema $schema, DocumentNode $ast, ?array $rules = null): bool
    {
        return $this->cache->has($this->cacheKey());
    }

    public function markValidated(Schema $schema, DocumentNode $ast, ?array $rules = null): void
    {
        $this->cache->put($this->cacheKey(), true, $this->ttl);
    }

    private function cacheKey(): string
    {
        $versions = (InstalledVersions::getVersion('webonyx/graphql-php') ?? '')
            . (InstalledVersions::getVersion('nuwave/lighthouse') ?? '');

        return "lighthouse:validation:{$this->schemaHash}:{$this->queryHash}:{$this->rulesConfigHash}:" . hash('sha256', $versions);
    }
}
