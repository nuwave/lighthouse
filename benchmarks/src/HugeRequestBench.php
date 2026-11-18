<?php declare(strict_types=1);

namespace Benchmarks;

use Tally\Attribute\DataProvider;
use Tally\Attribute\Warmup;

#[Warmup(1)]
final class HugeRequestBench extends QueryBench
{
    protected string $schema = /** @lang GraphQL */ <<<'GRAPHQL'
    type Query {
      foo: String!
        @field(resolver: "Benchmarks\\HugeRequestBench@resolve")
    }
    GRAPHQL;

    protected ?string $query = null;

    /**
     * Resolves foo.
     *
     * @skip
     */
    public function resolve(): string
    {
        return 'foo';
    }

    /** Generates query with $count fragments. */
    private function generateQuery(int $count): string
    {
        $query = '{';
        for ($i = 0; $i < $count; ++$i) {
            $query .= '...foo' . $i . PHP_EOL;
        }

        $query .= '}' . PHP_EOL;
        for ($i = 0; $i < $count; ++$i) {
            $query .= 'fragment foo' . $i . ' on Query {' . PHP_EOL;
            $query .= 'foo' . PHP_EOL;
            $query .= '}' . PHP_EOL;
        }

        return $query;
    }

    #[DataProvider('providePerformanceTuning')]
    public function benchmark1(bool ...$performanceTuning): void
    {
        $this->query ??= $this->generateQuery(1);
        $this->graphQL($this->query);
    }

    #[DataProvider('providePerformanceTuning')]
    public function benchmark10(bool ...$performanceTuning): void
    {
        $this->query ??= $this->generateQuery(10);
        $this->graphQL($this->query);
    }

    #[DataProvider('providePerformanceTuning')]
    public function benchmark100(bool ...$performanceTuning): void
    {
        $this->query ??= $this->generateQuery(100);
        $this->graphQL($this->query);
    }
}
