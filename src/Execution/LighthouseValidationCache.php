<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution;

use Composer\InstalledVersions;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Type\Schema;
use GraphQL\Validator\ValidationCache;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

class LighthouseValidationCache implements ValidationCache
{
    /** @var array<string> */
    protected const RELEVANT_PACKAGES = [
        'nuwave/lighthouse',
        'webonyx/graphql-php',
    ];

    protected string $packagesHash;

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

    protected function cacheKey(): string
    {
        return "lighthouse:validation:{$this->schemaHash}:{$this->queryHash}:{$this->rulesConfigHash}:{$this->packagesHash()}";
    }

    protected function packagesHash(): string
    {
        return $this->packagesHash ??= $this->buildPackagesHash();
    }

    protected function buildPackagesHash(): string
    {
        $versions = [];
        foreach (self::RELEVANT_PACKAGES as $package) {
            $versions[$package] = $this->requireVersion($package);
        }

        return hash('sha256', \Safe\json_encode($versions));
    }

    protected function requireVersion(string $package): string
    {
        return InstalledVersions::getVersion($package)
            ?? throw new \RuntimeException("Could not determine version of {$package} package.");
    }
}
