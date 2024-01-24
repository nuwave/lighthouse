<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Testing;

class MockResolverService
{
    /** @var array<string, callable> */
    protected array $mocks = [];

    /** Register a mock resolver that will be called through this resolver. */
    public function register(callable $mock, string $key): void
    {
        $this->mocks[$key] = $mock;
    }

    /** Return a mock resolver that was previously registered. */
    public function get(string $key): callable
    {
        return $this->mocks[$key]
            ?? throw new \Exception("No mock was registered for key: {$key}.");
    }
}
