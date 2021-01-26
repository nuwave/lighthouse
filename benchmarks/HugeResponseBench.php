<?php


namespace Benchmarks;


use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\OutputTimeUnit;

class HugeResponseBench extends QueryBench
{
    private $parent = null;

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
     * Resolves parent
     *
     * @skip
     */
    public function resolve() : array {
        if($this->parent)
            return $this->parent;

        $this->parent = [
            'name' => 'parent',
            'children' => [],
        ];

        for ($i = 0; $i < 100; $i++){
            $this->parent['children'][] = [
                'name' => 'children' . $i,
                'parent' => $this->parent,
            ];
        }
        return $this->parent;
    }

    /**
     * @Iterations(10)
     * @OutputTimeUnit("seconds", precision=3)
     */
    public function benchmark1() : void {
        $this->graphQL(/** @lang GraphQL */ "
        {
            parent {
                name
            }
        }
        ");
    }

    /**
     * @Iterations(10)
     * @OutputTimeUnit("seconds", precision=3)
     */
    public function benchmark100() : void {
        $this->graphQL(/** @lang GraphQL */ "
        {
            parent {
                children {
                    name
                }
            }
        }
        ");
    }

    /**
     * @Iterations(10)
     * @OutputTimeUnit("seconds", precision=3)
     */
    public function benchmark10k() : void {
        $this->graphQL(/** @lang GraphQL */ "
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
        ");
    }
}