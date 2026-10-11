<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Directives;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

final class InjectDirectiveDBTest extends DBTestCase
{
    public function testInjectDataFromContextIntoArgs(): void
    {
        $user = factory(User::class)->create();
        $this->be($user);

        $this->mockResolver()
            ->with(null, ['user_id' => 1]);

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type User {
            id: Int!
        }

        type Query {
            me: User
                @inject(context: "user.id", name: "user_id")
                @mock
        }
        GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            me {
                id
            }
        }
        GRAPHQL);
    }
}
