<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Tests\TestCase;
use Illuminate\Support\Arr;
use Tests\Utils\Resolvers\Foo;

class DeprecatedDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itCanRemoveDeprecatedFieldsFromIntrospection()
    {
        $reason = 'Use `bar` field';
        $resolver = addslashes(Foo::class).'@bar';
        $this->schema = "
        type Query {
            foo: String 
                @deprecated(reason: \"{$reason}\") 
                @field(resolver: \"{$resolver}\")
            bar: String
                @field(resolver: \"{$resolver}\")
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

        $this->query($introspectionQuery)->assertJsonCount(1, 'data.__schema.queryType.fields');

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

        $result = $this->query($includeDeprecatedIntrospectionQuery);
        $deprecatedFields = Arr::where(
            $result->json('data.__schema.queryType.fields'),
            function (array $field): bool {
                return $field['isDeprecated'];
            }
        );
        $this->assertCount(1, $deprecatedFields);
        $this->assertSame($reason, $deprecatedFields[0]['deprecationReason']);

        $this->query('
        {
            foo
        }
        ')->assertJson([
            'data' => [
                'foo' => 'foo.bar'
            ]
        ]);
    }
}
