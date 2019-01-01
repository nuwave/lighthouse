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
        $schema = "
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

        $data = $this->execute($schema, $introspectionQuery);
        $fields = Arr::get($data, 'data.__schema.queryType.fields');
        $this->assertCount(1, $fields);

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

        $data = $this->execute($schema, $includeDeprecatedIntrospectionQuery);
        $deprecatedFields = Arr::where(
            Arr::get($data, 'data.__schema.queryType.fields'),
            function ($field) {
                return $field['isDeprecated'];
            }
        );
        $this->assertCount(1, $deprecatedFields);
        $this->assertEquals($reason, $deprecatedFields[0]['deprecationReason']);

        $query = '
        {
            foo
        }
        ';

        $data = $this->execute($schema, $query);
        $this->assertSame('foo.bar', Arr::get($data, 'data.foo'));
    }
}
