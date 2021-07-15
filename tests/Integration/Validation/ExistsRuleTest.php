<?php

namespace Tests\Integration\Validation;

use Tests\DBTestCase;
use Tests\Utils\Models\User;

class ExistsRuleTest extends DBTestCase
{
    public function testExistsRule(): void
    {
        /** @var \Tests\Utils\Models\User $userValid */
        $userValid = factory(User::class)->make();
        $userValid->name = 'Admin';
        $userValid->save();

        /** @var \Tests\Utils\Models\User $userInvalid */
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

    /**
     * @return \Illuminate\Testing\TestResponse
     */
    protected function macroUser(User $user)
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

    /**
     * @return \Illuminate\Testing\TestResponse
     */
    protected function callbackUser(User $user)
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
