<?php declare(strict_types=1);

namespace Tests\Unit\Auth;

use Tests\Utils\Models\User;
use Tests\Utils\Policies\UserPolicy;

final class CanResolvedDirectiveTest extends CanDirectiveTestBase
{
    public static function getSchema(string $commonArgs): string
    {
        return /** @lang GraphQL */ "
            type Query {
                user(foo: String): User
                    @canResolved({$commonArgs})
                    @mock
            }

            type User {
                name: String
            }
        ";
    }

    public function testThrowsIfNotAuthorized(): void
    {
        $this->mockResolver(fn (): User => $this->resolveUser());
        parent::testThrowsIfNotAuthorized();
    }

    public function testThrowsWithCustomMessageIfNotAuthorized(): void
    {
        $this->mockResolver(fn (): User => $this->resolveUser());
        parent::testThrowsWithCustomMessageIfNotAuthorized();
    }

    public function testThrowsFirstWithCustomMessageIfNotAuthorized(): void
    {
        $this->mockResolver(fn (): User => $this->resolveUser());
        parent::testThrowsFirstWithCustomMessageIfNotAuthorized();
    }

    public function testReturnsValue(): void
    {
        $this->mockResolver(fn (): User => $this->resolveUser());
        parent::testReturnsValue();
    }

    public function testProcessesTheArgsArgument(): void
    {
        $this->mockResolver(fn (): User => $this->resolveUser());
        parent::testProcessesTheArgsArgument();
    }

    public function testChecksAgainstResolvedModels(): void
    {
        $user = new User();
        $user->name = UserPolicy::ADMIN;
        $this->be($user);

        $this->mockResolver(fn (): User => $this->resolveUser());

        $this->schema = $this->getSchema('ability: "view"');

        $this->graphQL($this->getQuery())->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foo',
                ],
            ],
        ]);
    }
}
