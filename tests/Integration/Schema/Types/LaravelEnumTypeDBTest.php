<?php

namespace Tests\Integration\Schema\Types;

use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Schema\Types\LaravelEnumType;
use Tests\DBTestCase;
use Tests\Utils\LaravelEnums\AOrB;
use Tests\Utils\Models\WithEnum;

class LaravelEnumTypeDBTest extends DBTestCase
{
    /**
     * @var \Nuwave\Lighthouse\Schema\TypeRegistry
     */
    protected $typeRegistry;

    public function setUp(): void
    {
        parent::setUp();

        $this->typeRegistry = $this->app->make(TypeRegistry::class);
    }

    public function testUseLaravelEnumType(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            withEnum(type: AOrB @eq): WithEnum @find
        }

        type Mutation {
            createWithEnum(type: AOrB): WithEnum @create
        }

        type WithEnum {
            type: AOrB
        }
        ';

        $this->typeRegistry->register(
            new LaravelEnumType(AOrB::class)
        );

        $typeA = [
            'type' => 'A',
        ];

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            createWithEnum(type: A) {
                type
            }
        }
        ')->assertJsonFragment($typeA);

        $this->graphQL(/** @lang GraphQL */ '
        {
            withEnum(type: A) {
                type
            }
        }
        ')->assertJsonFragment($typeA);
    }

    public function testWhereJsonContainsUsingEnumType(): void
    {
        // We use the "name" field to store the "type" JSON
        $this->schema = /** @lang GraphQL */ '
        type Query {
            withEnum(
                type: AOrB @whereJsonContains(key: "name")
            ): WithEnum @find
        }

        type WithEnum {
            name: String
        }
        ';

        $this->typeRegistry->register(
            new LaravelEnumType(AOrB::class)
        );

        $encodedType = \Safe\json_encode([AOrB::A]);

        $withEnum = new WithEnum();
        $withEnum->name = $encodedType;
        $withEnum->save();

        $this->graphQL(/** @lang GraphQL */ '
        {
            withEnum(type: A) {
                name
            }
        }
        ')->assertJson([
            'data' => [
                'withEnum' => [
                    'name' => $encodedType,
                ],
            ],
        ]);
    }
}
