<?php

namespace Tests\Unit\Schema\Directives;

use GraphQL\Type\Definition\Type;
use Illuminate\Support\Arr;
use Tests\DBTestCase;

class HasManyDirectiveTest extends DBTestCase
{
    public function testUsesEdgeTypeForRelayConnections(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type User {
            tasks: [Task!]! @hasMany (
                type: CONNECTION
                edgeType: "TaskEdge"
            )
        }

        type Task {
            id: Int
            foo: String
        }

        type TaskEdge {
            cursor: String!
            node: Task!
        }

        type Query {
            user: User @auth
        }
        ';

        $expectedConnectionName = 'TaskEdgeConnection';

        $this->assertNotEmpty(
            $this->introspectType($expectedConnectionName)
        );

        $user = $this->introspectType('User');
        $this->assertNotNull($user);

        /** @var array<string, mixed> $user */
        $tasks = Arr::first(
            $user['fields'],
            function (array $field): bool {
                return 'tasks' === $field['name'];
            }
        );
        $this->assertSame(
            $expectedConnectionName,
            $tasks['type']/* TODO add back in in v6 ['ofType'] */ ['name']
        );
    }

    public function testThrowsErrorWithUnknownTypeArg(): void
    {
        $this->expectExceptionMessage('Found invalid pagination type: foo');

        $schema = $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ '
        type User {
            tasks(first: Int! after: Int): [Task!]! @hasMany(type: "foo")
        }

        type Task {
            foo: String
        }
        ');

        $type = $schema->getType('User');

        $this->assertInstanceOf(Type::class, $type);
        /** @var \GraphQL\Type\Definition\Type $type */
        $type->config['fields']();
    }
}
