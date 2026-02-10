<?php declare(strict_types=1);

namespace Tests\Integration\Schema\Types;

use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Schema\Types\LaravelEnumType;
use Tests\DBTestCase;
use Tests\Utils\LaravelEnums\AOrB;
use Tests\Utils\Models\WithEnum;

final class LaravelEnumTypeDBTest extends DBTestCase
{
    protected TypeRegistry $typeRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->typeRegistry = $this->app->make(TypeRegistry::class);
    }

    public function testUseLaravelEnumType(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            withEnum(type: AOrB @eq): WithEnum @find
        }

        type Mutation {
            createWithEnum(type: AOrB): WithEnum @create
        }

        type WithEnum {
            type: AOrB
        }
        GRAPHQL;

        $this->typeRegistry->register(
            new LaravelEnumType(AOrB::class),
        );

        $typeA = [
            'type' => 'A',
        ];

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        mutation {
            createWithEnum(type: A) {
                type
            }
        }
        GRAPHQL)->assertJsonFragment($typeA);

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            withEnum(type: A) {
                type
            }
        }
        GRAPHQL)->assertJsonFragment($typeA);
    }

    public function testWhereJsonContainsUsingEnumType(): void
    {
        // We use the "name" field to store the "type" JSON
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            withEnum(
                type: AOrB @whereJsonContains(key: "name")
            ): WithEnum @find
        }

        type WithEnum {
            name: String
        }
        GRAPHQL;

        $this->typeRegistry->register(
            new LaravelEnumType(AOrB::class),
        );

        $encodedType = \Safe\json_encode([AOrB::A]);

        $withEnum = new WithEnum();
        $withEnum->name = $encodedType;
        $withEnum->save();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            withEnum(type: A) {
                name
            }
        }
        GRAPHQL)->assertJson([
            'data' => [
                'withEnum' => [
                    'name' => $encodedType,
                ],
            ],
        ]);
    }

    public function testScopeUsingEnumType(): void
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            withEnum(
                byType: AOrB @scope
            ): WithEnum @find
        }

        type WithEnum {
            type: AOrB
        }
        GRAPHQL;

        $this->typeRegistry->register(
            new LaravelEnumType(AOrB::class),
        );

        $a = AOrB::A();

        $withEnum = new WithEnum();
        $withEnum->type = $a;
        $withEnum->save();

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        query ($type: AOrB) {
            withEnum(byType: $type) {
                type
            }
        }
        GRAPHQL, [
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
