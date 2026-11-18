<?php declare(strict_types=1);

namespace Benchmarks;

use Tally\Attribute\DataProvider;
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
