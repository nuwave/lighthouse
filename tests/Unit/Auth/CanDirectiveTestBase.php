<?php declare(strict_types=1);

namespace Tests\Unit\Auth;

use Illuminate\Testing\TestResponse;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Tests\TestCase;
use Tests\Utils\Models\User;
use Tests\Utils\Policies\UserPolicy;

abstract class CanDirectiveTestBase extends TestCase
{
    abstract public static function getSchema(string $commonArgs): string;

    protected function getQuery(): string
    {
        return /** @lang GraphQL */ '
            query ($foo: String) {
                user(foo: $foo) {
                    name
                }
            }
        ';
    }

    protected function query(?string $foo = null): TestResponse
    {
        return $this->graphQL($this->getQuery(), ['foo' => $foo]);
    }

    public function testThrowsIfNotAuthorized(): void
    {
        $this->be(new User());

        $this->schema = $this->getSchema('ability: "adminOnly"');

        $this->query()->assertGraphQLErrorMessage(AuthorizationException::MESSAGE);
    }

    public function testThrowsWithCustomMessageIfNotAuthorized(): void
    {
        $this->be(new User());

        $this->schema = $this->getSchema('ability: "superAdminOnly"');

        $this->query()->assertGraphQLErrorMessage(UserPolicy::SUPER_ADMINS_ONLY_MESSAGE);
    }

    public function testThrowsFirstWithCustomMessageIfNotAuthorized(): void
    {
        $this->be(new User());

        $this->schema = $this->getSchema('ability: ["superAdminOnly", "adminOnly"]');

        $this->query()->assertGraphQLErrorMessage(UserPolicy::SUPER_ADMINS_ONLY_MESSAGE);
    }

    public function testConcealsCustomMessage(): void
    {
        $this->be(new User());

        $this->schema = $this->getSchema('ability: "superAdminOnly", action: EXCEPTION_NOT_AUTHORIZED');

        $this->query()->assertGraphQLErrorMessage(AuthorizationException::MESSAGE);
    }

    public function testReturnsValue(): void
    {
        $this->schema = $this->getSchema('ability: "superAdminOnly", action: RETURN_VALUE, returnValue: null');

        $this->query()->assertJson([
            'data' => [
                'user' => null,
            ],
        ]);
    }

    public function testPassesAuthIfAuthorized(): void
    {
        $user = new User();
        $user->name = UserPolicy::ADMIN;
        $this->be($user);

        $this->mockResolver(fn (): User => $this->resolveUser());

        $this->schema = $this->getSchema('ability: "adminOnly"');

        $this->query()->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public function testAcceptsGuestUser(): void
    {
        $this->mockResolver(fn (): User => $this->resolveUser());

        $this->schema = $this->getSchema('ability: "guestOnly"');

        $this->query()->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public function testPassesMultiplePolicies(): void
    {
        $user = new User();
        $user->name = UserPolicy::ADMIN;
        $this->be($user);

        $this->mockResolver(fn (): User => $this->resolveUser());

        $this->schema = $this->getSchema('ability: ["adminOnly", "alwaysTrue"]');

        $this->query()->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public function testProcessesTheArgsArgument(): void
    {
        $this->schema = $this->getSchema('ability: "dependingOnArg", args: [false]');

        $this->query()->assertGraphQLErrorMessage(AuthorizationException::MESSAGE);
    }

    public function testInjectArgsPassesClientArgumentToPolicy(): void
    {
        $this->be(new User());

        $this->mockResolver(fn (): User => $this->resolveUser());

        $this->schema = $this->getSchema('ability: "injectArgs", injectArgs: [true]');

        $this->query('bar')->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public function testInjectedArgsAndStaticArgs(): void
    {
        $this->be(new User());

        $this->mockResolver(fn (): User => $this->resolveUser());

        $this->schema = $this->getSchema('ability: "argsWithInjectedArgs", args: { foo: "static" }, injectArgs: true');

        $this->query('dynamic')->assertJson([
            'data' => [
                'user' => [
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public static function resolveUser(): User
    {
        $user = new User();
        $user->name = 'foo';
        $user->email = 'test@example.com';

        return $user;
    }
}
