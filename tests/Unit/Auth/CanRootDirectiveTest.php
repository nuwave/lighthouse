<?php declare(strict_types=1);

namespace Tests\Unit\Auth;

use Illuminate\Contracts\Auth\Access\Gate;
use Tests\Utils\Models\User;
use Tests\Utils\Policies\UserPolicy;

final class CanRootDirectiveTest extends CanDirectiveTestBase
{
    public static function getSchema(string $commonArgs): string
    {
        return /** @lang GraphQL */ "
            type Query {
                user: User!
                    @mock
            }

            type User {
                name(foo: String): String @canRoot({$commonArgs})
            }
        ";
    }

    protected function getQuery(): string
    {
        return /** @lang GraphQL */ '
            query ($foo: String) {
                user {
                    name (foo: $foo)
                }
            }
        ';
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

    public function testConcealsCustomMessage(): void
    {
        $this->mockResolver(fn (): User => $this->resolveUser());
        parent::testConcealsCustomMessage();
    }

    public function testProcessesTheArgsArgument(): void
    {
        $this->mockResolver(fn (): User => $this->resolveUser());
        parent::testProcessesTheArgsArgument();
    }

    public function testReturnsValue(): void
    {
        $this->mockResolver(fn (): User => $this->resolveUser());

        $this->schema = $this->getSchema('ability: "superAdminOnly", action: RETURN_VALUE, returnValue: "concealed"');

        $this->query()->assertJson([
            'data' => [
                'user' => [
                    'name' => 'concealed',
                ],
            ],
        ]);
    }

    public function testChecksAgainstModel(): void
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
        $this->mockResolver(static fn (): object => $return);

        $this->app
            ->make(Gate::class)
            ->define('customObject', static fn (User $authorizedUser, object $root): bool => $authorizedUser === $user && $root === $return);

        $this->schema = $this->getSchema('ability: "customObject"');

        $this->query()->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public function testChecksAgainstArray(): void
    {
        $user = new User();
        $user->name = UserPolicy::ADMIN;
        $this->be($user);

        $return = ['name' => 'foo'];
        $this->mockResolver(static fn (): array => $return);

        $this->app
            ->make(Gate::class)
            ->define('customArray', static fn (User $authorizedUser, array $root): bool => $authorizedUser === $user && $root == $return);

        $this->schema = $this->getSchema('ability: "customArray"');

        $this->query()->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public function testGlobalGate(): void
    {
        $user = new User();
        $this->be($user);

        $this->app->make(Gate::class)->define('globalAdmin', static fn ($authorizedUser): bool => $authorizedUser === $user);

        $this->mockResolver(fn (): User => $this->resolveUser());

        $this->schema = /** @lang GraphQL */ '
            type Query {
                user: User!
                    @canRoot(ability: "globalAdmin")
                    @mock
            }

            type User {
                name(foo: String): String
            }
        ';

        $this->query()->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foo',
                ],
            ],
        ]);
    }
}
