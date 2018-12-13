<?php

namespace Tests\Integration;

use Tests\TestCase;
use Illuminate\Support\Arr;
use Tests\Utils\Queries\Foo;

class ValidationTest extends TestCase
{
    protected $schema = '
    type Query {
        foo(
            email: String = "hans@peter.rudolf" @rules(apply: ["email"])
            required: String @rules(apply: ["required"])
            input: [Bar] @rulesForArray(apply: ["min:3"])
            list: [String]
                @rules(apply: ["required", "email"])
                @rulesForArray(apply: ["max:2"])
        ): Int
        
        password(
            id: String
            password: String
                @trim
                @rules(apply: ["min:6", "max:20", "required_with:id"])
                @bcrypt
        ): String @field(resolver: "Tests\\\\Integration\\\\ValidationTest@resolvePassword")
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

    public function resolvePassword($root, array $args): string
    {
        return $args['password'] ?? 'no-password';
    }

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
                    "valid_email@example.com"
                    null
                    "invalid_email"
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

        $this->assertSame($expected, $result);
    }

    /**
     * @test
     */
    public function itEvaluatesArgDirectivesInDefinitionOrder()
    {
        $validPasswordQuery = '
        {
            password(password: " 1234567 ")
        }
        ';
        $result = graphql()->executeQuery($validPasswordQuery)->toArray();

        $password = Arr::get($result, 'data.password');
        $this->assertNotSame('password', ' 1234567 ');
        $this->assertTrue(password_verify('1234567', $password));

        $invalidPasswordQuery = '
        {
            password(password: " 1234 ")
        }
        ';
        $result = graphql()->executeQuery($invalidPasswordQuery)->toArray();

        $password = Arr::get($result, 'data.password');
        $this->assertNull($password);
        $this->assertValidationKeysSame(['password'], $result);
    }

    /**
     * @test
     */
    public function itEvaluatesConditionalValidation()
    {
        $validPasswordQuery = '
        {
            password
        }
        ';
        $result = graphql()->executeQuery($validPasswordQuery)->toArray();

        $this->assertSame('no-password', Arr::get($result, 'data.password'));

        $invalidPasswordQuery = '
        {
            password(id: "foo")
        }
        ';
        $result = graphql()->executeQuery($invalidPasswordQuery)->toArray();

        $password = Arr::get($result, 'data.password');
        $this->assertNull($password);
        $this->assertValidationKeysSame(['password'], $result);
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
        $validation = Arr::get($result, 'errors.0.extensions.validation');
        $this->assertSame($keys, array_keys($validation));
    }
}
