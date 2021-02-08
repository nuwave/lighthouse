<?php

namespace Tests\Unit\Schema\Directives;

use GraphQL\Type\Definition\Directive;
use Illuminate\Support\Arr;
use Tests\TestCase;

class DeprecatedDirectiveTest extends TestCase
{
    public function testCanRemoveDeprecatedFieldsFromIntrospection(): void
    {
        $reason = 'Use `bar` field';
        $this->schema = /** @lang GraphQL */ "
        type Query {
            withReason: String @mock
                @deprecated(reason: \"{$reason}\")
            withDefaultReason: String @mock
                @deprecated
            notDeprecated: String @mock
        }

        enum Foo {
            DEPRECATED @deprecated
            NOT_DEPRECATED
        }
        ";

        $introspectionQuery = /** @lang GraphQL */ '
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
        ';
        $withoutDeprecatedIntrospection = $this->graphQL(
            $introspectionQuery,
            [
                'includeDeprecated' => false,
            ]
        );

        $withoutDeprecatedIntrospection->assertJsonCount(1, 'data.__schema.queryType.fields');
        $types = $withoutDeprecatedIntrospection->json('data.__schema.types');
        $foo = Arr::first($types, static function (array $type): bool {
            return $type['name'] === 'Foo';
        });
        $this->assertCount(1, $foo['enumValues']);

        $includeDeprecatedIntrospection = $this->graphQL(
            $introspectionQuery,
            [
                'includeDeprecated' => true,
            ]
        );

        $deprecatedFields = Arr::where(
            $includeDeprecatedIntrospection->json('data.__schema.queryType.fields'),
            function (array $field): bool {
                return $field['isDeprecated'];
            }
        );
        $this->assertCount(2, $deprecatedFields);
        $this->assertSame(
            $reason,
            $deprecatedFields[0]['deprecationReason'],
            'Should show user-defined deprecation reason.'
        );
        $this->assertSame(
            Directive::DEFAULT_DEPRECATION_REASON,
            $deprecatedFields[1]['deprecationReason'],
            'Should fallback to the default deprecation reason'
        );

        $types = $includeDeprecatedIntrospection->json('data.__schema.types');
        $foo = Arr::first($types, static function (array $type): bool {
            return $type['name'] === 'Foo';
        });
        $this->assertCount(2, $foo['enumValues']);
    }
}
