<?php

namespace Tests\Integration\Schema\Types;

use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Schema\Types\LaravelEnumType;
use Tests\DBTestCase;
use Tests\Utils\LaravelEnums\UserType;
use Tests\Utils\Models\User;

class LaravelEnumTypeDBTest extends DBTestCase
{
    /**
     * @var \Nuwave\Lighthouse\Schema\TypeRegistry
     */
    protected $typeRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->typeRegistry = $this->app->make(TypeRegistry::class);
    }

    public function testUseLaravelEnumType(): void
    {
        $this->schema = '
        type Query {
            user(type: UserType @eq): User @find
        }

        type Mutation {
            createUser(type: UserType): User @create
        }

        type User {
            type: UserType
        }
        ';

        $this->typeRegistry->register(
            new LaravelEnumType(UserType::class)
        );

        $typeAdmistrator = [
            'type' => 'Administrator',
        ];

        $this->graphQL('
        mutation {
            createUser(type: Administrator) {
                type
            }
        }
        ')->assertJsonFragment($typeAdmistrator);

        $this->graphQL('
        {
            user(type: Administrator) {
                type
            }
        }
        ')->assertJsonFragment($typeAdmistrator);
    }

    public function testWhereJsonContainsUsingEnumType(): void
    {
        if ((float) $this->app->version() < 5.6) {
            $this->markTestSkipped('Laravel supports whereJsonContains from version 5.6.');
        }

        // We use the "name" field to store the "type" JSON
        $this->schema = '
        type Query {
            user(
                type: UserType @whereJsonContains(key: "name")
            ): User @find
        }

        type User {
            name: String
        }
        ';

        $this->typeRegistry->register(
            new LaravelEnumType(UserType::class)
        );

        $encodedType = json_encode([UserType::Administrator]);

        $user = new User();
        $user->name = $encodedType;
        $user->save();

        $this->graphQL('
        {
            user(type: Administrator) {
                name
            }
        }
        ')->assertJson([
            'data' => [
                'user' => [
                    'name' => $encodedType,
                ],
            ],
        ]);
    }
}
