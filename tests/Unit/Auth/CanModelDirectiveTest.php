<?php declare(strict_types=1);

namespace Tests\Unit\Auth;

final class CanModelDirectiveTest extends CanDirectiveTestBase
{
    public static function getSchema(string $commonArgs): string
    {
        return /** @lang GraphQL */ "
            type Query {
                user(foo: String): User
                    @canModel({$commonArgs})
                    @mock
            }

            type User {
                name: String
            }
        ";
    }
}
