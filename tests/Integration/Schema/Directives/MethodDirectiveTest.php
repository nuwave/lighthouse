<?php

namespace Tests\Integration\Schema\Directives;

use Illuminate\Support\Str;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Tests\DBTestCase;
use Tests\Utils\Models\User;

/**
 * Class MethodDirectiveTest.
 */
class MethodDirectiveTest extends DBTestCase
{
    public function testCanCallModelMethodWithParameters(): void
    {
        $user = factory(User::class)->create();

        $this->schema = '
            type User {
                name(case: String): String
                    @method(name: "getName", pass: ["case"])
            }

            type Query {
                user: User @auth
            }
        ';

        $this->actingAs($user)->graphQL('
            {
                user {
                    name(case: "uppercase")
                }
            }
        ')->assertJsonFragment([
            'name' => Str::upper($user->name),
        ]);
    }

    public function testShouldThrowExceptionOnInvalidPassArguments(): void
    {
        $user = factory(User::class)->create();

        $this->schema = '
            type User {
                name(case: String): String
                    @method(name: "getName", pass: ["invalid"])
            }

            type Query {
                user: User @auth
            }
        ';

        $this->expectException(DirectiveException::class);
        $this->expectExceptionMessage('No field argument for the pass element: invalid');

        $response = $this->actingAs($user)->graphQL('
            {
                user {
                    name(case: "uppercase")
                }
            }
        ');
    }
}
