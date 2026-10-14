<?php declare(strict_types=1);

namespace Tests\Unit\Schema\Directives;

use GraphQL\Type\Definition\Directive;
use Illuminate\Support\Arr;
use Tests\TestCase;

final class DeprecatedDirectiveTest extends TestCase
{
    public function testRemoveDeprecatedFieldsFromIntrospection(): void
    {
        $reason = 'Use `bar` field';
        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        type Query {
            withReason: String @mock
                @deprecated(reason: "{$reason}")
            withDefaultReason: String @mock
                @deprecated
            notDeprecated: String @mock
        }

        enum Foo {
            DEPRECATED @deprecated
            NOT_DEPRECATED
        }
        GRAPHQL;

        $introspectionQuery = /** @lang GraphQL */ <<<'GRAPHQL'
        query ($includeDeprecated: Boolean!) {
            __schema {
                queryType {
                    fields(includeDeprecated: $includeDeprecated) {
                        name
                        isDeprecated
                        deprecationReason
                    }
                }
                types {
                    name
                    enumValues(includeDeprecated: $includeDeprecated) {
                        name
                        isDeprecated
                        deprecationReason
                    }
                }
            }
        }
        GRAPHQL;
        $withoutDeprecatedIntrospection = $this->graphQL(
            $introspectionQuery,
            [
                'includeDeprecated' => false,
            ],
        );

        $withoutDeprecatedIntrospection->assertJsonCount(1, 'data.__schema.queryType.fields');

        $types = $withoutDeprecatedIntrospection->json('data.__schema.types');
        $foo = Arr::first($types, static fn (array $type): bool => $type['name'] === 'Foo');
        $this->assertCount(1, $foo['enumValues'] ?? 0);

        $includeDeprecatedIntrospection = $this->graphQL(
            $introspectionQuery,
            [
                'includeDeprecated' => true,
            ],
        );

        $deprecatedFields = Arr::where(
            $includeDeprecatedIntrospection->json('data.__schema.queryType.fields'),
            static fn (array $field): bool => $field['isDeprecated'],
        );
        $this->assertCount(2, $deprecatedFields);
        $this->assertSame(
            $reason,
            $deprecatedFields[0]['deprecationReason'],
            'Should show user-defined deprecation reason.',
        );
        $this->assertSame(
            Directive::DEFAULT_DEPRECATION_REASON,
            $deprecatedFields[1]['deprecationReason'],
            'Should fallback to the default deprecation reason',
        );

        $types = $includeDeprecatedIntrospection->json('data.__schema.types');
        $foo = Arr::first($types, static fn (array $type): bool => $type['name'] === 'Foo');
        $this->assertCount(2, $foo['enumValues'] ?? 0);
    }
}
