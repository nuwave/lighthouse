<?php declare(strict_types=1);

namespace Tests\Unit\Auth;

use Illuminate\Contracts\Auth\Access\Gate;
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

        $this->query()->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public function testChecksAgainstObject(): void
    {
        $user = new User();
        $user->name = UserPolicy::ADMIN;
        $this->be($user);

        $return = new class() {
            public string $name = 'foo';
        };
        $this->mockResolver(fn (): object => $return);

        $this->app
            ->make(Gate::class)
            ->define('customObject', fn (User $authorizedUser, object $root) => $authorizedUser === $user && $root == $return);

        $this->schema = $this->getSchema('ability: "customObject"');

        $this->query()->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foo',
                ],
            ],
        ]);
    }
}
