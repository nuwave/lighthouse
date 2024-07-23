<?php declare(strict_types=1);

namespace Benchmarks;

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

    /**
     * @Iterations(10)
     *
     * @OutputTimeUnit("seconds", precision=3)
     */
    public function benchmark1(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        {
            parent {
                name
            }
        }
        ');
    }

    /**
     * @Iterations(10)
     *
     * @OutputTimeUnit("seconds", precision=3)
     */
    public function benchmark100(): void
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

    /**
     * @Iterations(10)
     *
     * @OutputTimeUnit("seconds", precision=3)
     */
    public function benchmark10k(): void
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
