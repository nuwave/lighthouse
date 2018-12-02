<?php

namespace Tests\Integration;

use Tests\TestCase;
use Tests\Utils\Queries\Foo;

class ValidationTest extends TestCase
{
    protected $schema = '
    type Query {
        foo(
            email: String = "hans@peter.rudolf" @rules(apply: ["email"])
            required: String @rules(apply: ["required"])
            input: [Bar] @rulesForArray(apply: ["min:3"])
            list: [String] @rules(apply: ["required", "email"]) @rulesForArray(apply: ["max:2"])
        ): Int
    }
    
    input Bar {
        foobar: Int @rules(apply: ["integer", "max:10"])
        self: Bar
        withRequired: Baz
        optional: String
    }
    
    input Baz {
        barbaz: Int
        invalidDefault: String = "invalid-mail" @rules(apply: ["email"])
        required: Int @rules(apply: ["required"])
    }
    ';

    /**
     * @test
     */
    public function itValidatesDifferentPathsIndividually()
    {
        $query = '
        {
            foo(
                input: [
                    {
                        foobar: 123
                    }
                    {
                        self: {
                            foobar: 12
                        }
                    }
                    {
                        withRequired: {
                            barbaz: 23
                        }
                    }    
                ]
            )
        }
        ';

        $result = graphql()->executeQuery($query)->toArray();

        $this->assertValidationKeysSame([
            'required',
            'input.0.foobar',
            'input.1.self.foobar',
            'input.2.withRequired.invalidDefault',
            'input.2.withRequired.required',
        ], $result);
    }

    /**
     * @test
     */
    public function itValidatesList()
    {
        $query = '
        {
            foo(
                list: [
                    "asdf@asfd.com"
                    null
                    "asdfasdf"
                ]
            )
        }
        ';

        $result = graphql()->executeQuery($query)->toArray();

        $this->assertValidationKeysSame([
            'required',
            'list',
            'list.1',
            'list.2',
        ], $result);
    }

    /**
     * @test
     */
    public function itValidatesInputCount()
    {
        $query = '
        {
            foo(
                input: [{
                    foobar: 1
                }]
            )
        }
        ';

        $result = graphql()->executeQuery($query)->toArray();

        $this->assertValidationKeysSame([
            'required',
            'input',
        ], $result);
    }

    /**
     * @test
     */
    public function itPassesIfNothingRequiredIsMissing()
    {
        $query = '
        {
            foo(required: "foo")
        }
        ';

        $result = graphql()->executeQuery($query)->toArray();

        $expected = [
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function itErrorsIfSomethingRequiredIsMissing()
    {
        $query = '
        {
            foo
        }
        ';

        $result = graphql()->executeQuery($query)->toArray();

        $expected = [
            'data' => [
                'foo' => null,
            ],
        ];
        $this->assertArraySubset($expected, $result);
        $this->assertValidationKeysSame(['required'], $result);
    }

    protected function assertValidationKeysSame(array $keys, array $result)
    {
        $validation = array_get($result, 'errors.0.extensions.validation');
        foreach ($keys as $key) {
            $this->assertNotNull(data_get($validation, $key));
        }
    }
}
