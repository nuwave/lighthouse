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

        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            callbackUser(id: ID! @eq): User @validator @find
            macroUser(id: ID! @eq): User @validator @find
        }

        type User {
            id: ID!
        }
        GRAPHQL;

        $this->macroUser($userValid)
            ->assertGraphQLValidationPasses();

        $this->callbackUser($userValid)
            ->assertGraphQLValidationPasses();

        $this->macroUser($userInvalid)
            ->assertGraphQLValidationError('id', 'The selected id is invalid.');

        $this->callbackUser($userInvalid)
            ->assertGraphQLValidationError('id', 'The selected id is invalid.');
    }

    private function macroUser(User $user): TestResponse
    {
        return $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($id: ID!) {
            macroUser(id: $id) {
                id
            }
        }
        GRAPHQL, [
            'id' => $user->id,
        ]);
    }

    private function callbackUser(User $user): TestResponse
    {
        return $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($id: ID!) {
            callbackUser(id: $id) {
                id
            }
        }
        GRAPHQL, [
            'id' => $user->id,
        ]);
    }
}
