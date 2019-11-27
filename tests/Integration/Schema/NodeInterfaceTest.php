<?php

namespace Tests\Integration\Schema;

use Nuwave\Lighthouse\Support\Contracts\GlobalId;
use Tests\DBTestCase;
use Tests\Utils\Models\User;

class NodeInterfaceTest extends DBTestCase
{
    /**
     * @var \Nuwave\Lighthouse\Support\Contracts\GlobalId
     */
    private $globalIdResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->globalIdResolver = app(GlobalId::class);
    }

    /**
     * @var mixed[]
     */
    protected $testTuples = [
        1 => [
            'id' => 1,
            'name' => 'foobar',
        ],
        2 => [
            'id' => 2,
            'name' => 'barbaz',
        ],
    ];

    public function testCanResolveNodes(): void
    {
        $this->schema .= '
        type User @node(resolver: "Tests\\\Integration\\\Schema\\\NodeInterfaceTest@resolveNode") {
            name: String!
        }
        ';

        $firstGlobalId = $this->globalIdResolver->encode('User', $this->testTuples[1]['id']);
        $secondGlobalId = $this->globalIdResolver->encode('User', $this->testTuples[2]['id']);

        $this->graphQL('
        {
            first: node(id: "'.$firstGlobalId.'") {
                id
                ...on User {
                    name
                }
            }
            second: node(id: "'.$secondGlobalId.'") {
                id
                ...on User {
                    name
                }
            }
        }
        ')->assertExactJson([
            'data' => [
                'first' => [
                    'id' => $firstGlobalId,
                    'name' => $this->testTuples[1]['name'],
                ],
                'second' => [
                    'id' => $secondGlobalId,
                    'name' => $this->testTuples[2]['name'],
                ],
            ],
        ]);
    }

    /**
     * @param  int  $id
     * @return mixed[]
     */
    public function resolveNode(int $id): array
    {
        return $this->testTuples[$id];
    }

    /**
     * @dataProvider modelNodeDirectiveStyles
     * @param  string  $directiveDefinition
     */
    public function testCanResolveModelsNodes(string $directiveDefinition): void
    {
        $this->schema .= "
        type User $directiveDefinition {
            name: String!
        }
        ";

        $user = factory(User::class)->create([
            'name' => 'Sepp',
        ]);
        $globalId = $this->globalIdResolver->encode('User', $user->getKey());

        $this->graphQL('
        {
            node(id: "'.$globalId.'") {
                id
                ...on User {
                    name
                }
            }
        }
        ')->assertExactJson([
            'data' => [
                'node' => [
                    'id' => $globalId,
                    'name' => 'Sepp',
                ],
            ],
        ]);
    }

    /**
     * @return string[][]
     */
    public function modelNodeDirectiveStyles(): array
    {
        return [
            /*
             * @deprecated @model will be repurposed in v5
             */
            ['@model'],
            ['@node'],
            ['@node(model: "User")'],
        ];
    }
}
