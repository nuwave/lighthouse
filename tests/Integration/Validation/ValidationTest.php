<?php

namespace Tests\Integration\Validation;

use Illuminate\Foundation\Testing\TestResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Tests\DBTestCase;
use Tests\Utils\Queries\Foo;

class ValidationTest extends DBTestCase
{
    protected $schema = /** @lang GraphQL */ '
    type Query {
        foo(
            email: String = "hans@peter.rudolf" @rules(apply: ["email"])
            required: String @rules(apply: ["required"])
            stringList: [String!] @rulesForArray(apply: ["array", "max:1"])
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
                @hash
            bar: Bar
                @rules(apply: ["required_if:id,bar"])
        ): String @field(resolver: "Tests\\\\Integration\\\\Validation\\\\ValidationTest@resolvePassword")

        email(
            userId: ID!
            email: Email!
        ): String @field(resolver: "Tests\\\\Integration\\\\Validation\\\\ValidationTest@resolveEmail")
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

    public function testRunsValidationBeforeCallingTheResolver(): void
    {
        $this->mockResolverExpects(
            $this->never()
        );

        $this->schema = /** @lang GraphQL */ '
        type Query {
            doNotCall(
                bar: String @rules(apply: ["required"])
            ): String @mock
        }
        ';

        $response = $this->graphQL(/** @lang GraphQL */ '
        {
            doNotCall
        }
        ');

        $this->assertValidationKeysSame(
            ['bar'],
            $response
        );
    }

    public function testValidatesDifferentPathsIndividually(): void
    {
        $result = $this->graphQL(/** @lang GraphQL */ '
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

        $this->assertValidationKeysSame(
            [
                'required',
                'input.0.foobar',
                'input.1.self.foobar',
                'input.2.withRequired.invalidDefault',
                'input.2.withRequired.required',
            ],
            $result
        );
    }

    public function testValidatesList(): void
    {
        $result = $this->graphQL(/** @lang GraphQL */ '
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

    public function testValidatesInputCount(): void
    {
        $result = $this->graphQL(/** @lang GraphQL */ '
        {
            foo(
                stringList: [
                    "asdf",
                    "one too many"
                ]
                input: [{
                    foobar: 1
                }]
            )
        }
        ');

        $this->assertValidationKeysSame([
            'required',
            'stringList',
            'input',
        ], $result);

        $this->assertTrue(
            Str::endsWith(
                $result->jsonGet('errors.0.extensions.validation.stringList.0'),
                'may not have more than 1 items.'
            )
        );

        $this->assertTrue(
            Str::endsWith(
                $result->jsonGet('errors.0.extensions.validation.input.0'),
                'must have at least 3 items.'
            ),
            'Validate size as an array by prepending the rules with the "array" validation'
        );
    }

    public function testPassesIfNothingRequiredIsMissing(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(required: "foo")
        }
        ')->assertJson([
            'data' => [
                'foo' => Foo::THE_ANSWER,
            ],
        ]);
    }

    public function testEvaluatesArgDirectivesInDefinitionOrder(): void
    {
        $validPasswordResult = $this->graphQL(/** @lang GraphQL */ '
        {
            password(password: " 1234567 ")
        }
        ');
        $password = $validPasswordResult->jsonGet('data.password');

        $this->assertNotSame(' 1234567 ', $password);
        $this->assertTrue(password_verify('1234567', $password));

        $invalidPasswordResult = $this->graphQL(/** @lang GraphQL */ '
        {
            password(password: " 1234 ")
        }
        ')->assertJson([
            'data' => [
                'password' => null,
            ],
        ]);

        $this->assertValidationKeysSame(['password'], $invalidPasswordResult);
    }

    public function testEvaluatesConditionalValidation(): void
    {
        $validPasswordResult = $this->graphQL(/** @lang GraphQL */ '
        {
            password
        }
        ');

        $this->assertSame('no-password', $validPasswordResult->jsonGet('data.password'));

        $invalidPasswordResult = $this->graphQL(/** @lang GraphQL */ '
        {
            password(id: "foo")
        }
        ')->assertJson([
            'data' => [
                'password' => null,
            ],
        ]);

        $this->assertValidationKeysSame(['password'], $invalidPasswordResult);
    }

    public function testEvaluatesInputArgValidation(): void
    {
        $result = $this->graphQL(/** @lang GraphQL */ '
        {
            password(id: "bar", password: "123456")
        }
        ')->assertJson([
            'data' => [
                'password' => null,
            ],
        ]);

        $this->assertValidationKeysSame(['bar'], $result);
    }

    public function testEvaluatesNonNullInputArgValidation(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
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
            ],
        ]);

        $invalidEmailResult = $this->graphQL(/** @lang GraphQL */ '
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
            ],
        ]);
        $this->assertValidationKeysSame([
            'email.emailAddress',
            'email.business',
        ], $invalidEmailResult);
    }

    public function testErrorsIfSomethingRequiredIsMissing(): void
    {
        $result = $this->graphQL(/** @lang GraphQL */ '
        {
            foo
        }
        ')->assertJson([
            'data' => [
                'foo' => null,
            ],
        ]);

        $this->assertValidationKeysSame([
            'required',
        ], $result);
    }

    public function testCombinesMultipleRules(): void
    {
        $this->markTestSkipped('
        This should work once we can reliably depend upon repeatable directives.
        As of now, the rules of the second @rules directive are not considered
        and Lighthouse uses those of the first directive.
        ');

        $this->schema .= /** @lang GraphQL */ '
        type Mutation {
            createUser(
                foo: String @rules(apply: ["max:5"]) @rules(apply: ["min:4"])
            ): User
                @create
        }

        type User {
            id: ID
            name: String
        }
        ';
    }

    public function testCombinesArgumentValidationWhenGrouped(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        type Mutation {
            withMergedRules(
                bar: String
                    @rules(apply: ["min:1"])
                    @customRules(apply: ["bool"])
            ): User @create
        }

        type User {
            id: ID
            name: String
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            withMergedRules(bar: "abcdefghijk") {
                name
            }
        }
        ')->assertJsonCount(1, 'errors.0.extensions.validation.bar');
    }

    /**
     * Assert that the returned result contains an exactly defined array of validation keys.
     *
     * @param  array  $keys
     * @param  \Illuminate\Foundation\Testing\TestResponse  $result
     * @return void
     */
    protected function assertValidationKeysSame(array $keys, TestResponse $result): void
    {
        $validation = $result->jsonGet('errors.0.extensions.validation');

        $this->assertSame($keys, array_keys($validation));
    }
}
