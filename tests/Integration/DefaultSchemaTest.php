<?php declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Testing\TestResponse;
use Nuwave\Lighthouse\Console\ValidateSchemaCommand;
use Nuwave\Lighthouse\LighthouseServiceProvider;
use Nuwave\Lighthouse\Pagination\PaginationServiceProvider;
use Nuwave\Lighthouse\Testing\TestingServiceProvider;
use Nuwave\Lighthouse\Validation\ValidationServiceProvider;
use Tests\DBTestCase;
use Tests\Utils\Models\User;

final class DefaultSchemaTest extends DBTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schema = \Safe\file_get_contents(__DIR__ . '/../../src/default-schema.graphql');
    }

    /**
     * The default schema should work with a minimal setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     *
     * @return array<class-string<\Illuminate\Support\ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LighthouseServiceProvider::class,
            PaginationServiceProvider::class,
            TestingServiceProvider::class,
            ValidationServiceProvider::class,
        ];
    }

    public function testPassesValidation(): void
    {
        $tester = $this->commandTester(new ValidateSchemaCommand());
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testFindRequiresExactlyOneArgument(): void
    {
        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                user {
                    id
                }
            }
            ')
            ->assertGraphQLValidationError('email', 'The email field is required when id is not present.')
            ->assertGraphQLValidationError('id', 'The id field is required when email is not present.');

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                user(id: 1, email: "foo@bar.baz") {
                    id
                }
            }
            ')
            ->assertGraphQLValidationError('email', 'The email field prohibits id from being present.')
            ->assertGraphQLValidationError('id', 'The id field prohibits email from being present.');
    }

    public function testFindById(): void
    {
        factory(User::class)->create();
        $user = factory(User::class)->create();

        $this
            ->graphQL(/** @lang GraphQL */ '
            query ($id: ID!) {
                user(id: $id) {
                    id
                }
            }
            ', [
                'id' => $user->id,
            ])
            ->assertExactJson([
                'data' => [
                    'user' => [
                        'id' => "{$user->id}",
                    ],
                ],
            ]);
    }

    public function testEmptyUsers(): void
    {
        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                users {
                    data {
                        id
                    }
                }
            }
            ')
            ->assertExactJson([
                'data' => [
                    'users' => [
                        'data' => [],
                    ],
                ],
            ]);
    }

    public function testAllUsers(): void
    {
        $amount = 2;
        factory(User::class)->times($amount)->create();

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                users {
                    data {
                        id
                    }
                }
            }
            ')
            ->assertJsonCount(2, 'data.users.data');
    }

    public function testUsersByName(): void
    {
        $name = 'foo';

        $user = factory(User::class)->make();
        $user->name = $name;
        $user->save();

        $this->usersByName($name)
            ->assertJsonCount(1, 'data.users.data');

        $this->usersByName('fo%')
            ->assertJsonCount(1, 'data.users.data');

        $this->usersByName('%oo')
            ->assertJsonCount(1, 'data.users.data');

        $this->usersByName('bar')
            ->assertJsonCount(0, 'data.users.data');
    }

    protected function usersByName(string $name): TestResponse
    {
        return $this->graphQL(/** @lang GraphQL */ '
            query ($name: String!) {
                users(name: $name) {
                    data {
                        id
                    }
                }
            }
            ',
            [
                'name' => $name,
            ],
        );
    }
}
