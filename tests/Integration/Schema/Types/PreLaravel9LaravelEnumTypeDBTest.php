<?php

namespace Tests\Integration\Schema\Types;

use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Schema\Types\LaravelEnumType;
use Nuwave\Lighthouse\Support\AppVersion;
use Tests\DBTestCase;
use Tests\Utils\LaravelEnums\AOrB;
use Tests\Utils\Models\PreLaravel9WithEnum;

/**
 * TODO remove when requiring Laravel 9+.
 */
final class PreLaravel9LaravelEnumTypeDBTest extends DBTestCase
{
    /**
     * @var \Nuwave\Lighthouse\Schema\TypeRegistry
     */
    protected $typeRegistry;

    public function setUp(): void
    {
        parent::setUp();

        if (AppVersion::atLeast(9.0)) {
            $this->markTestSkipped('Uses pre-Laravel 9 style enums');
        } else {
            $this->typeRegistry = $this->app->make(TypeRegistry::class);
        }
    }

    public function testUseLaravelEnumType(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            withEnum(type: AOrB @eq): PreLaravel9WithEnum @find
        }

        type Mutation {
            createWithEnum(type: AOrB): PreLaravel9WithEnum @create
        }

        type PreLaravel9WithEnum {
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
            ): PreLaravel9WithEnum @find
        }

        type PreLaravel9WithEnum {
            name: String
        }
        ';

        $this->typeRegistry->register(
            new LaravelEnumType(AOrB::class)
        );

        $encodedType = \Safe\json_encode([AOrB::A]);

        $withEnum = new PreLaravel9WithEnum();
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

    public function testScopeUsingEnumType(): void
    {
        $this->schema = /** @lang GraphQL */ '
        type Query {
            withEnum(
                byType: AOrB @scope
                byTypeInternal: AOrB @scope # TODO remove in v6
            ): PreLaravel9WithEnum @find
        }

        type PreLaravel9WithEnum {
            type: AOrB
        }
        ';

        $this->typeRegistry->register(
            new LaravelEnumType(AOrB::class)
        );

        $a = AOrB::A();

        $withEnum = new PreLaravel9WithEnum();
        $withEnum->type = $a;
        $withEnum->save();

        // TODO remove in v6
        $this->graphQL(/** @lang GraphQL */ '
        query ($type: AOrB) {
            withEnum(byTypeInternal: $type) {
                type
            }
        }
        ', [
            'type' => $a->key,
        ])->assertJson([
            'data' => [
                'withEnum' => [
                    'type' => $a->key,
                ],
            ],
        ]);
        config(['lighthouse.unbox_bensampo_enum_enum_instances' => false]);

        $this->graphQL(/** @lang GraphQL */ '
        query ($type: AOrB) {
            withEnum(byType: $type) {
                type
            }
        }
        ', [
            'type' => $a->key,
        ])->assertJson([
            'data' => [
                'withEnum' => [
                    'type' => $a->key,
                ],
            ],
        ]);
    }
}
