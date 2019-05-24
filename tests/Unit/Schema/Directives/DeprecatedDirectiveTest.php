<?php

namespace Tests\Unit\Schema\Directives;

use Tests\TestCase;
use Illuminate\Support\Arr;
use Tests\Utils\Resolvers\Foo;
use GraphQL\Type\Definition\Directive;

class DeprecatedDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itCanRemoveDeprecatedFieldsFromIntrospection(): void
    {
        $reason = 'Use `bar` field';
        $resolver = addslashes(Foo::class).'@bar';
        $this->schema = "
        type Query {
            foo: String @field(resolver: \"{$resolver}\")
                @deprecated(reason: \"{$reason}\") 
            withDefaultReason: String @field(resolver: \"{$resolver}\")
                @deprecated
            bar: String @field(resolver: \"{$resolver}\")
        }
        ";

        $introspectionQuery = '
        {
            __schema {
                queryType {
                    fields {
                        name
                    }
                }
            }
        }
        ';
        $this->graphQL($introspectionQuery)
            ->assertJsonCount(1, 'data.__schema.queryType.fields');

        $includeDeprecatedIntrospectionQuery = '
        {
            __schema {
                queryType {
                    fields(includeDeprecated: true) {
                        name
                        isDeprecated
                        deprecationReason
                    }
                }
            }
        }
        ';
        $result = $this->graphQL($includeDeprecatedIntrospectionQuery);

        $deprecatedFields = Arr::where(
            $result->jsonGet('data.__schema.queryType.fields'),
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

        $this->graphQL('
        {
            foo
        }
        ')->assertJson([
            'data' => [
                'foo' => 'foo.bar',
            ],
        ]);
    }
}
