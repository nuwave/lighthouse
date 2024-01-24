<?php declare(strict_types=1);

namespace Tests\Integration\Validation;

use Illuminate\Testing\TestResponse;
use Tests\DBTestCase;
use Tests\Utils\Models\User;

final class ExistsRuleTest extends DBTestCase
{
    public function testExistsRule(): void
    {
        /** @var User $userValid */
        $userValid = factory(User::class)->make();
        $userValid->name = 'Admin';
        $userValid->save();

        /** @var User $userInvalid */
        $userInvalid = factory(User::class)->make();
        $userInvalid->name = 'Tester';
        $userInvalid->save();

        $this->schema = /** @lang GraphQL */ '
        type Query {
            callbackUser(id: ID! @eq): User @validator @find
            macroUser(id: ID! @eq): User @validator @find
        }

        type User {
            id: ID!
        }
        ';

        $this->macroUser($userValid)
            ->assertGraphQLValidationPasses();

        $this->callbackUser($userValid)
            ->assertGraphQLValidationPasses();

        $this->macroUser($userInvalid)
            ->assertGraphQLValidationError('id', 'The selected id is invalid.');

        $this->callbackUser($userInvalid)
            ->assertGraphQLValidationError('id', 'The selected id is invalid.');
    }

    protected function macroUser(User $user): TestResponse
    {
        return $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID!) {
            macroUser(id: $id) {
                id
            }
        }
        ', [
            'id' => $user->id,
        ]);
    }

    protected function callbackUser(User $user): TestResponse
    {
        return $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID!) {
            callbackUser(id: $id) {
                id
            }
        }
        ', [
            'id' => $user->id,
        ]);
    }
}
