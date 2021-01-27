<?php

namespace Benchmarks;

/**
 * @BeforeMethods({"setUp"})
 */
class HugeResponseBench extends QueryBench
{
    /**
     * Cached value of parent with recursive children.
     *
     * @var array{name: string, children: array<int, array{name: string, array<string, mixed>}>}
     */
    protected $parent;

    protected $schema = /** @lang GraphQL */ <<<'GRAPHQL'
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
     * @return array{name: string, children: array<int, array{name: string, array<string, mixed>}>}
     */
    public function resolve(): array
    {
        if (isset($this->parent)) {
            return $this->parent;
        }

        $this->parent = [
            'name' => 'parent',
            'children' => [],
        ];

        for ($i = 0; $i < 100; $i++) {
            $this->parent['children'][] = [
                'name' => "child {$i}",
                'parent' => $this->parent,
            ];
        }

        return $this->parent;
    }

    /**
     * @Iterations(10)
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
