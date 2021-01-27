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
     * @var array<string, string|array>
     */
    protected $parent = null;

    protected $schema = /** @lang GraphQL */ <<<'GRAPHQL'
type Query {
  parent: Parent
    @field(resolver: "Benchmarks\\HugeResponseBench@resolve")
}

type Parent {
  name: String!
  children: [Children!]!
}

type Children {
  name: String!
  parent: Parent!
}

GRAPHQL;

    /**
     * Resolves parent.
     *
     * @skip
     * @return array<string, string|array>
     */
    public function resolve(): array
    {
        if ($this->parent) {
            return $this->parent;
        }

        $this->parent = [
            'name' => 'parent',
            'children' => [],
        ];

        for ($i = 0; $i < 100; $i++) {
            $this->parent['children'][] = [
                'name' => 'children'.$i,
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
