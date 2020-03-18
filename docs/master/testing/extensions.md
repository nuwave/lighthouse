# Testing Lighthouse extensions

When you extend Lighthouse with custom functionality, it is a great idea to test
your extensions in isolation from the rest of your application.

## Use a test schema

When you enhance functionality related to the schema definition, such as adding
a [custom directive](../custom-directives), you need a test schema where you can use it.
Add the `UsesTestSchema` trait to your test class, call `setUpTestSchema()` and define your test schema:

```php
<?php

namespace Tests;

use Nuwave\Lighthouse\Testing\UsesTestSchema;

class MyCustomDirectiveTest extends TestCase
{
    use UsesTestSchema;

    // You may set the schema once and use it in many test methods
    protected $schema = /** @lang GraphQL */ '
    type Query {
        foo: Int @myCustom
    }
    ';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTestSchema();;
    }

    public function testSpecificScenario(): void
    {
        // You can overwrite the schema for testing specific cases
        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(bar: String @myCustom): Int
        }
        ';

        // ...
    }
}
```

## Mock resolvers

When testing custom functionality through a dummy schema, you still need to have
a way to resolve fields. Lighthouse provides a simple way to mock resolvers in a dummy schema.

Add the `MocksResolvers` trait to your test class:

```php
<?php

namespace Tests;

use Nuwave\Lighthouse\Testing\MocksResolvers;

class ReverseDirectiveTest extends TestCase
{
    use MocksResolvers;
}
```

In this example, we will be testing this fictional custom directive:

```graphql
"""
Reverts a string, e.g. 'foo' => 'oof'.
"""
directive @revert on FIELD_DEFINITION | ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

The simplest way to mock a resolver is to have it return static data:

```php
public function testReverseField(): void
{
    $this->mockResolver('foo');

    $this->schema = /** @lang GraphQL */ '
    type Query {
        foo: String @reverse @mock
    }
    ';

    $this->graphQL(/** @lang GraphQL */ '
    {
        foo
    }
    ')->assertExactJson([
        'data' => [
            'foo' => 'oof',
        ],
    ]);
}
```

Since we get back an instance of PHPUnit's `InvocationMocker`, we can also assert
that our resolver is called with certain values. Note that we are not passing an
explicit resolver function here. The default resolver will simply return `null`.

```php
public function testReverseInput(): void
{
    $this->mockResolver()
        ->with(null, ['bar' => 'rab']);

    $this->schema = /** @lang GraphQL */ '
    type Query {
        foo(bar: String @reverse): String @mock
    }
    ';

    $this->graphQL(/** @lang GraphQL */ '
    {
        foo(bar: "bar")
    }
    ')->assertExactJson([
        'data' => [
            'foo' => null,
        ],
    ]);
}
```

If you have to handle the incoming resolver arguments dynamically, you can also
pass a function that is called:

```php
public function testReverseInput(): void
{
    $this->mockResolver(function($root, array $args): string {
        return $args['bar'];
    });

    $this->schema = /** @lang GraphQL */ '
    type Query {
        foo(bar: String @reverse): String @mock
    }
    ';

    $this->graphQL(/** @lang GraphQL */ '
    {
        foo(bar: "bar")
    }
    ')->assertExactJson([
        'data' => [
            'foo' => 'rab',
        ],
    ]);
}
```

You might have a need to add multiple resolvers to a single schema. For that case,
specify a unique `key` for the mock resolver (it defaults to `default`):

```php
public function testMultipleResolvers(): void
{
    $this->mockResolver(..., 'first');
    $this->mockResolver(..., 'second');

    $this->schema = /** @lang GraphQL */ '
    type Query {
        foo: Int @mock(key: "first")
        bar: ID @mock(key: "second")
    }
    ';
}
```

By default, the resolver from `mockResolver` expects to be called at least once.
If you want to set a different expectation, you can use `mockResolverExpects`:

```php
public function testAbortsBeforeResolver(): void
{
    $this->mockResolverExpects(
        $this->never()
    );

    $this->schema = /** @lang GraphQL */ '
    type Query {
        foo: Int @someValidationThatFails @mock
    }
    ';

    $this->graphQL(/** @lang GraphQL */ '
    {
        foo
    }
    ');
}
```
