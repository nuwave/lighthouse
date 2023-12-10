<?php declare(strict_types=1);

namespace Auth;

use Tests\Unit\Auth\CanDirectiveTestBase;
use Tests\Utils\Models\User;
use Tests\Utils\Policies\UserPolicy;

class CanModelDirectiveTest extends CanDirectiveTestBase
{
    static function getSchema(string $commonArgs): string
    {
        return /** @lang GraphQL */ "
            type Query {
                user(foo: String): User
                    @canModel($commonArgs)
                    @mock
            }

            type User {
                name: String
            }
        ";
    }
}
