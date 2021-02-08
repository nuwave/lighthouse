<?php

namespace Tests\Integration\Schema;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Support\Contracts\GlobalId;
use Tests\DBTestCase;
use Tests\Utils\Models\User;

class NodeDirectiveDBTest extends DBTestCase
{
    /**
     * @var \Nuwave\Lighthouse\Support\Contracts\GlobalId
     */
    protected $globalIdResolver;

    public function setUp(): void
    {
        parent::setUp();

        $this->globalIdResolver = app(GlobalId::class);
    }

    /**
     * @var array<int, array<string, mixed>>
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
        $this->schema .= /** @lang GraphQL */ '
        type User @node(resolver: "Tests\\\Integration\\\Schema\\\NodeDirectiveDBTest@resolveNode") {
            name: String!
        }
        ';

        $firstGlobalId = $this->globalIdResolver->encode('User', $this->testTuples[1]['id']);
        $secondGlobalId = $this->globalIdResolver->encode('User', $this->testTuples[2]['id']);

        $this->graphQL(/** @lang GraphQL */ "
        {
            first: node(id: \"{$firstGlobalId}\") {
                id
                ...on User {
                    name
                }
            }
            second: node(id: \"{$secondGlobalId}\") {
                id
                ...on User {
                    name
                }
            }
        }
        ")->assertExactJson([
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

    public function testCanResolveNodesViaInterface(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        interface IUser {
            name: String!
        }
        type User implements IUser @node(resolver: "Tests\\\Integration\\\Schema\\\NodeDirectiveDBTest@resolveNode") {
            name: String!
        }
        ';

        $globalId = $this->globalIdResolver->encode('User', $this->testTuples[1]['id']);

        $this->graphQL(/** @lang GraphQL */ "
        {
            node: node(id: \"{$globalId}\") {
                id
                ...on IUser {
                    name
                }
            }
        }
        ")->assertExactJson([
            'data' => [
                'node' => [
                    'id' => $globalId,
                    'name' => $this->testTuples[1]['name'],
                ],
            ],
        ]);
    }

    public function testUnknownNodeType(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type User @node(resolver: "Tests\\\Integration\\\Schema\\\NodeDirectiveDBTest@resolveNode") {
            name: String!
        }
        ';

        $globalId = $this->globalIdResolver->encode('WrongClass', $this->testTuples[1]['id']);
        $this->graphQL(/** @lang GraphQL */ "
        {
            node: node(id: \"{$globalId}\") {
                id
            }
        }
        ")->assertJson([
            'data' => [
                'node' => null,
            ],
            'errors' => [
                [
                    'message' => '[WrongClass] is not a type and cannot be resolved.',
                ],
            ],
        ]);
    }

    public function testTypeWithoutNodeDirective(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type User @node(resolver: "Tests\\\Integration\\\Schema\\\NodeDirectiveDBTest@resolveNode") {
            name: String!
        }

        type User2 {
            name: String!
        }
        ';

        $globalId = $this->globalIdResolver->encode('User2', $this->testTuples[1]['id']);
        $this->graphQL(/** @lang GraphQL */ "
        {
            node: node(id: \"{$globalId}\") {
                id
            }
        }
        ")->assertJson([
            'data' => [
                'node' => null,
            ],
            'errors' => [
                [
                    'message' => '[User2] is not a registered node and cannot be resolved.',
                ],
            ],
        ]);
    }

    /**
     * @return array<mixed>
     */
    public function resolveNode(int $id): array
    {
        return $this->testTuples[$id];
    }

    /**
     * @dataProvider modelNodeDirectiveStyles
     */
    public function testCanResolveModelsNodes(string $directiveDefinition): void
    {
        $this->schema .= /** @lang GraphQL */"
        type User {$directiveDefinition} {
            name: String!
        }
        ";

        $user = factory(User::class)->create([
            'name' => 'Sepp',
        ]);
        $globalId = $this->globalIdResolver->encode('User', $user->getKey());

        $this->graphQL(/** @lang GraphQL */ "
        {
            node(id: \"{$globalId}\") {
                id
                ...on User {
                    name
                }
            }
        }
        ")->assertExactJson([
            'data' => [
                'node' => [
                    'id' => $globalId,
                    'name' => 'Sepp',
                ],
            ],
        ]);
    }

    /**
     * @return array<array<string>>
     */
    public function modelNodeDirectiveStyles(): array
    {
        return [
            ['@node'],
            ['@node(model: "User")'],
        ];
    }

    public function testThrowsWhenNodeDirectiveIsDefinedOnNonObjectType(): void
    {
        $this->expectException(DefinitionException::class);
        $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ '
        input Foo @node {
            bar: ID
        }
        ');
    }
}
