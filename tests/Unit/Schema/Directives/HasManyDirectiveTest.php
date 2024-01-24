<?php declare(strict_types=1);

namespace Tests\Unit\Schema\Directives;

use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Tests\DBTestCase;

final class HasManyDirectiveTest extends DBTestCase
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
            $this->introspectType($expectedConnectionName),
        );

        $user = $this->introspectType('User');
        $this->assertNotNull($user);

        /** @var array<string, mixed> $user */
        $tasks = Arr::first(
            $user['fields'],
            static fn (array $field): bool => $field['name'] === 'tasks',
        );
        $this->assertSame(
            $expectedConnectionName,
            $tasks['type']['ofType']['name'],
        );
    }

    public function testThrowsErrorWithUnknownTypeArg(): void
    {
        $this->expectExceptionObject(new DefinitionException('Found invalid pagination type: foo'));

        $this->buildSchemaWithPlaceholderQuery(/** @lang GraphQL */ '
        type User {
            tasks(first: Int! after: Int): [Task!]! @hasMany(type: "foo")
        }

        type Task {
            foo: String
        }
        ');
    }
}
