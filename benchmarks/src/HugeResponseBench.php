<?php declare(strict_types=1);

namespace Benchmarks;

use Tally\Attribute\DataProvider;
use Tally\Attribute\ExpectedFor;
use Tally\Attribute\Warmup;

#[Warmup(1)]
final class HugeResponseBench extends QueryBench
{
    protected string $schema = /** @lang GraphQL */ <<<'GRAPHQL'
    type Query {
      parent: Parent
        @field(resolver: "Benchmarks\\HugeResponseBench@resolve")
    }

    type Parent {
      name: String!
      children: [Child!]!
    }

    type Child {
      name: String!
      parent: Parent!
    }
    GRAPHQL;

    /**
     * Resolves parent.
     *
     * @skip
     *
     * @return array<string, mixed>
     */
    public function resolve(): array
    {
        /** @var array<string, mixed>|null $parent */
        static $parent;
        if (! isset($parent)) {
            $parent = [
                'name' => 'parent',
                'children' => [],
            ];

            for ($i = 0; $i < 100; ++$i) {
                $parent['children'][] = [
                    'name' => "child {$i}",
                    'parent' => $parent,
                ];
            }
        }

        return $parent;
    }

    #[DataProvider('providePerformanceTuning')]
    #[ExpectedFor('everything', instructions: '3.003M', cycles: '4.511M', memory: '61.76KiB')]
    #[ExpectedFor('nothing', instructions: '3.959M', cycles: '5.758M', memory: '92.79KiB')]
    #[ExpectedFor('query + validation cache', instructions: '3.098M', cycles: '4.820M', memory: '61.76KiB')]
    #[ExpectedFor('query cache', instructions: '3.978M', cycles: '5.933M', memory: '90.21KiB')]
    public function benchmark1(bool ...$performanceTuning): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        {
            parent {
                name
            }
        }
        ');
    }

    #[DataProvider('providePerformanceTuning')]
    #[ExpectedFor('everything', instructions: '7.771M', cycles: '11.33M', memory: '79.55KiB')]
    #[ExpectedFor('nothing', instructions: '9.111M', cycles: '13.14M', memory: '94.96KiB')]
    #[ExpectedFor('query + validation cache', instructions: '7.911M', cycles: '11.74M', memory: '79.55KiB')]
    #[ExpectedFor('query cache', instructions: '9.089M', cycles: '13.15M', memory: '90.68KiB')]
    public function benchmark100(bool ...$performanceTuning): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        {
            parent {
                children {
                    name
                }
            }
        }
        ');
    }

    #[DataProvider('providePerformanceTuning')]
    #[ExpectedFor('everything', instructions: '247.3M', cycles: '353.1M', memory: '2.119MiB')]
    #[ExpectedFor('nothing', instructions: '249.5M', cycles: '354.8M', memory: '2.121MiB')]
    #[ExpectedFor('query + validation cache', instructions: '247.6M', cycles: '353.1M', memory: '2.119MiB')]
    #[ExpectedFor('query cache', instructions: '249.3M', cycles: '354.5M', memory: '2.113MiB')]
    public function benchmark10k(bool ...$performanceTuning): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        {
            parent {
                children {
                    parent {
                        children {
                            name
                        }
                    }
                }
            }
        }
        ');
    }
}
