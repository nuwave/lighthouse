<?php declare(strict_types=1);

namespace Benchmarks;

use Tally\Attribute\DataProvider;
use Tally\Attribute\ExpectedFor;
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
    #[ExpectedFor('everything', instructions: '2.938M', cycles: '4.387M', memory: '61.73KiB')]
    #[ExpectedFor('nothing', instructions: '4.263M', cycles: '6.046M', memory: '93.13KiB')]
    #[ExpectedFor('query + validation cache', instructions: '2.987M', cycles: '4.618M', memory: '61.73KiB')]
    #[ExpectedFor('query cache', instructions: '4.219M', cycles: '6.082M', memory: '87.98KiB')]
    public function benchmark1(bool ...$performanceTuning): void
    {
        $this->query ??= $this->generateQuery(1);
        $this->graphQL($this->query);
    }

    #[DataProvider('providePerformanceTuning')]
    #[ExpectedFor('everything', instructions: '5.221M', cycles: '7.213M', memory: '63.20KiB')]
    #[ExpectedFor('nothing', instructions: '15.99M', cycles: '19.80M', memory: '111.6KiB')]
    #[ExpectedFor('query + validation cache', instructions: '5.270M', cycles: '7.505M', memory: '63.20KiB')]
    #[ExpectedFor('query cache', instructions: '14.78M', cycles: '18.53M', memory: '75.38KiB')]
    public function benchmark10(bool ...$performanceTuning): void
    {
        $this->query ??= $this->generateQuery(10);
        $this->graphQL($this->query);
    }

    #[DataProvider('providePerformanceTuning')]
    #[ExpectedFor('everything', instructions: '28.08M', cycles: '36.32M', memory: '348.9KiB')]
    #[ExpectedFor('nothing', instructions: '207.3M', cycles: '254.5M', memory: '530.7KiB')]
    #[ExpectedFor('query + validation cache', instructions: '28.14M', cycles: '36.94M', memory: '348.9KiB')]
    #[ExpectedFor('query cache', instructions: '194.4M', cycles: '241.4M', memory: '339.3KiB')]
    public function benchmark100(bool ...$performanceTuning): void
    {
        $this->query ??= $this->generateQuery(100);
        $this->graphQL($this->query);
    }
}
