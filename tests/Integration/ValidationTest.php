<?php

namespace Tests\Integration;

use Tests\TestCase;
use Illuminate\Support\Arr;
use Tests\Utils\Queries\Foo;
use Illuminate\Foundation\Testing\TestResponse;

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
            bar: Bar
                @rules(apply: ["required_if:id,bar"])
        ): String @field(resolver: "Tests\\\\Integration\\\\ValidationTest@resolvePassword")

        email(
            userId: ID!
            email: Email!
        ): String @field(resolver: "Tests\\\\Integration\\\\ValidationTest@resolveEmail")
    }

    input Email {
        emailAddress: String! @rules(apply: ["email"])
        business: Boolean @rules(apply: ["required"])
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
     * @param  mixed  $root
     * @param  mixed[]  $args
     * @return string
     */
    public function resolvePassword($root, array $args): string
    {
        return $args['password'] ?? 'no-password';
    }

    /**
     * @param  mixed  $root
     * @param  mixed[]  $args
     * @return string
     */
    public function resolveEmail($root, array $args): string
    {
        return Arr::get($args, 'email.emailAddress', 'no-email');
    }

    /**
     * @test
     */
    public function itValidatesDifferentPathsIndividually(): void
    {
        $result = $this->query('
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
        ');

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
    public function itValidatesList(): void
    {
        $result = $this->query('
        {
            foo(
                list: [
                    "valid_email@example.com"
                    null
                    "invalid_email"
                ]
            )
        }
        ');

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
    public function itValidatesInputCount(): void
    {
        $result = $this->query('
        {
            foo(
                input: [{
                    foobar: 1
                }]
            )
        }
        ');

        $this->assertValidationKeysSame([
            'required',
            'input',
        ], $result);
    }

    /**
     * @test
     */
    public function itPassesIfNothingRequiredIsMissing(): void
    {
        $this->query('
        {
            foo(required: "foo")
        }
        ')->assertJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);
    }

    /**
     * @test
     */
    public function itEvaluatesArgDirectivesInDefinitionOrder(): void
    {
        $validPasswordResult = $this->query('
        {
            password(password: " 1234567 ")
        }
        ');
        $password = $validPasswordResult->json('data.password');

        $this->assertNotSame(' 1234567 ', $password);
        $this->assertTrue(\password_verify('1234567', $password));

        $invalidPasswordResult = $this->query('
        {
            password(password: " 1234 ")
        }
        ')->assertJson([
            'data' => [
                'password' => null,
            ]
        ]);

        $this->assertValidationKeysSame(['password'], $invalidPasswordResult);
    }

    /**
     * @test
     */
    public function itEvaluatesConditionalValidation(): void
    {
        $validPasswordResult = $this->query('
        {
            password
        }
        ');

        $this->assertSame('no-password', $validPasswordResult->json('data.password'));

        $invalidPasswordResult = $this->query('
        {
            password(id: "foo")
        }
        ')->assertJson([
            'data' => [
                'password' => null,
            ]
        ]);

        $this->assertValidationKeysSame(['password'], $invalidPasswordResult);
    }

    /**
     * @test
     */
    public function itEvaluatesInputArgValidation(): void
    {
        $result = $this->query('
        {
            password(id: "bar", password: "123456")
        }
        ')->assertJson([
            'data' => [
                'password' => null,
            ]
        ]);

        $this->assertValidationKeysSame(['bar'], $result);
    }

    /**
     * @test
     */
    public function itEvaluatesNonNullInputArgValidation(): void
    {
        $this->query('
        {
            email(
                userId: 1
                email: {
                    emailAddress: "john@doe.com"
                    business: true
                }
            )
        }
        ')->assertJson([
            'data' => [
                'email' => 'john@doe.com',
            ]
        ]);

        $invalidEmailResult = $this->query('
        {
            email(
                userId: 1
                email: {
                    emailAddress: "invalid_email_address"
                }
            )
        }
        ')->assertJson([
            'data' => [
                'email' => null,
            ]
        ]);
        $this->assertValidationKeysSame([
            'email.emailAddress',
            'email.business',
        ], $invalidEmailResult);
    }

    /**
     * @test
     */
    public function itErrorsIfSomethingRequiredIsMissing(): void
    {
        $result = $this->query('
        {
            foo
        }
        ')->assertJson([
            'data' => [
                'foo' => null,
            ],
        ]);

        $this->assertValidationKeysSame([
            'required'
        ], $result);
    }

    /**
     * Assert that the returned result contains an exactly defined array of validation keys.
     *
     * @param array        $keys
     * @param TestResponse $result
     *
     * @return void
     */
    protected function assertValidationKeysSame(array $keys, TestResponse $result): void
    {
        $validation = $result->json('errors.0.extensions.validation');

        $this->assertSame($keys, array_keys($validation));
    }
}
