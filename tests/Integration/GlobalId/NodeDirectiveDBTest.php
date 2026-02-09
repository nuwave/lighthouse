<?php declare(strict_types=1);

namespace Tests\Integration\GlobalId;

use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\GlobalId\GlobalId;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\DBTestCase;
use Tests\Utils\Models\User;

final class NodeDirectiveDBTest extends DBTestCase
{
    private GlobalId $globalIdResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->globalIdResolver = $this->app->make(GlobalId::class);
    }

    /** @var array<int, array<string, mixed>> */
    private const TEST_TUPLES = [
        1 => [
            'id' => 1,
            'name' => 'foobar',
        ],
        2 => [
            'id' => 2,
            'name' => 'barbaz',
        ],
    ];

    public function testResolveNodes(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type User @node(resolver: "Tests\\\Integration\\\GlobalId\\\NodeDirectiveDBTest@resolveNode") {
            name: String!
        }
        ';

        $firstGlobalId = $this->globalIdResolver->encode('User', self::TEST_TUPLES[1]['id']);
        $secondGlobalId = $this->globalIdResolver->encode('User', self::TEST_TUPLES[2]['id']);

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
                    'name' => self::TEST_TUPLES[1]['name'],
                ],
                'second' => [
                    'id' => $secondGlobalId,
                    'name' => self::TEST_TUPLES[2]['name'],
                ],
            ],
        ]);
    }

    public function testResolveNodesViaInterface(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        interface IUser {
            name: String!
        }
        type User implements IUser @node(resolver: "Tests\\\Integration\\\GlobalId\\\NodeDirectiveDBTest@resolveNode") {
            name: String!
        }
        ';

        $globalId = $this->globalIdResolver->encode('User', self::TEST_TUPLES[1]['id']);

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
                    'name' => self::TEST_TUPLES[1]['name'],
                ],
            ],
        ]);
    }

    public function testUnknownNodeType(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type User @node(resolver: "Tests\\\Integration\\\GlobalId\\\NodeDirectiveDBTest@resolveNode") {
            name: String!
        }
        ';

        $globalId = $this->globalIdResolver->encode('WrongClass', self::TEST_TUPLES[1]['id']);
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

        $globalId = $this->globalIdResolver->encode('User2', self::TEST_TUPLES[1]['id']);
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

    /** @return array<mixed> */
    public static function resolveNode(int|string $id): array
    {
        return self::TEST_TUPLES[$id];
    }

    /** @dataProvider modelNodeDirectiveStyles */
    #[DataProvider('modelNodeDirectiveStyles')]
    public function testResolveModelsNodes(string $directiveDefinition): void
    {
        $this->schema .= /** @lang GraphQL */ "
        type User {$directiveDefinition} {
            name: String!
        }
        ";

        $user = factory(User::class)->make();
        $this->assertInstanceOf(User::class, $user);
        $user->name = 'Sepp';
        $user->save();

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

    /** @return iterable<array{string}> */
    public static function modelNodeDirectiveStyles(): iterable
    {
        yield ['@node'];
        yield ['@node(model: "User")'];
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

    public function testPreservesCustomNodeField(): void
    {
        $result = 42;
        $this->mockResolver($result);

        $this->schema .= /** @lang GraphQL */ '
        type Query {
            # Nonsensical example, just done this way for ease of testing.
            # Usually customization would have the purpose of adding middleware.
            node: Int! @mock
        }

        type User @node {
            name: String!
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            node
        }
        ')->assertExactJson([
            'data' => [
                'node' => $result,
            ],
        ]);
    }
}
