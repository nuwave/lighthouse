<?php declare(strict_types=1);

namespace Integration\Auth;

use Tests\DBTestCase;
use Tests\Utils\Models\User;
use Tests\Utils\Mutations\ThrowWhenInvoked;
use Tests\Utils\Policies\UserPolicy;

final class CanModelDirectiveDBTest extends DBTestCase
{
    public function testDoesntConcealResolverException(): void
    {
        $admin = new User();
        $admin->name = UserPolicy::ADMIN;
        $this->be($admin);

        $this->schema = /** @lang GraphQL */
            '
        type Mutation {
            throwWhenInvoked: Task
                @canModel(ability: "adminOnly", action: EXCEPTION_NOT_AUTHORIZED)
        }

        type Task {
            name: String!
        }
        ' . self::PLACEHOLDER_QUERY;

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            throwWhenInvoked {
                name
            }
        }
        ')->assertGraphQLErrorMessage(ThrowWhenInvoked::ERROR_MESSAGE);
    }
}
