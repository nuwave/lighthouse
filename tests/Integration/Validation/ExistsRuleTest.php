<?php

namespace Tests\Integration\Validation;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Support\Contracts\GlobalId;
use Tests\DBTestCase;
use Tests\TestCase;
use Tests\Utils\Models\User;
use Tests\Utils\Validators\EmailCustomAttributeValidator;
use Tests\Utils\Validators\EmailCustomMessageValidator;

class ExistsRuleTest extends DBTestCase
{
    public function testExistsRule(): void
    {
        $userValid = factory(User::class)->create([
            'name' => 'Admin',
            'created_at' => null
        ]);
        $userInvalid = factory(User::class)->create([
            'name' => 'Tester',
            'created_at' => '2021-01-01 00:00:00'
        ]);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            getFaultyUser(input: GetFaultyUser): User @find
            getWorkingUser(input: GetWorkingUser): User @find
        }

        type User {
            id: ID!
        }

        input GetFaultyUser @validator {
            user_id: ID! @eq(key: "id")
        }
        input GetWorkingUser @validator {
            user_id: ID! @eq(key: "id")
        }
        ';

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                getWorkingUser(
                    input: {
                        user_id: '.$userValid->id.'
                    }
                ) {
                    id
                }
            }
            ')->assertGraphQLValidationPasses();

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                getFaultyUser(
                    input: {
                        user_id: '.$userValid->id.'
                    }
                ) {
                    id
                }
            }
            ')->assertGraphQLValidationPasses();

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                getWorkingUser(
                    input: {
                        user_id: '.$userInvalid->id.'
                    }
                ) {
                    id
                }
            }
            ')->assertGraphQLValidationError('input.user_id', 'The selected input.user id is invalid.');

        $this
            ->graphQL(/** @lang GraphQL */ '
            {
                getFaultyUser(
                    input: {
                        user_id: '.$userInvalid->id.'
                    }
                ) {
                    id
                }
            }
            ')->assertGraphQLValidationError('input.user_id', 'The selected input.user id is invalid.');
    }

    public function testLaravelValidator(): void
    {
        $userValid = factory(User::class)->create([
            'name' => 'Admin',
            'created_at' => null
        ]);
        $userInvalid = factory(User::class)->create([
            'name' => 'Tester',
            'created_at' => '2021-01-01 00:00:00'
        ]);

        $errors = Validator::make([
            'user_id' => $userValid->id
        ], [
            'user_id' => ['required', Rule::exists('users', 'id')
                ->where('name', 'Admin')
                ->whereNull('created_at'),
            ]
        ])->errors();
        $this->assertTrue($errors->isEmpty());

        $errors = Validator::make([
            'user_id' => $userValid->id
        ], [
            'user_id' => ['required', Rule::exists('users', 'id')->where(function ($query) {
                return $query->where('name', '=', 'Admin')
                    ->whereNull('created_at');
            })]
        ])->errors();
        $this->assertTrue($errors->isEmpty());

        $errors = Validator::make([
            'user_id' => $userInvalid->id
        ], [
            'user_id' => ['required', Rule::exists('users', 'id')
                ->where('name', 'Admin')
                ->whereNull('created_at'),
            ]
        ])->errors();
        $this->assertEquals('The selected user id is invalid.', $errors->first());

        $errors = Validator::make([
            'user_id' => $userInvalid->id
        ], [
            'user_id' => ['required', Rule::exists('users', 'id')->where(function ($query) {
                return $query->where('name', '=', 'Admin')
                    ->whereNull('created_at');
            })]
        ])->errors();
        $this->assertEquals('The selected user id is invalid.', $errors->first());

    }
}
