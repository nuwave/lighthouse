# Testing with PHPUnit

Lighthouse makes it easy to add automated tests through PHPUnit.

## Setup

Lighthouse offers some useful test helpers that make it easy to call your API
from within a PHPUnit test. Just add the `MakesGraphQLRequests` trait to your test class.

```diff
<?php

namespace Tests;

+use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
+   use MakesGraphQLRequests;
}
```

## Running Queries

The most natural way of testing your GraphQL API is to run actual GraphQL queries.

The `graphQL` test helper runs a query on your GraphQL endpoint and returns a `TestResponse`.

```php
public function testQueriesPosts(): void
{
    $response = $this->graphQL(/** @lang GraphQL */ '
    {
        posts {
            id
            title
        }
    }
    ');
}
```

If you want to use variables within your query, pass an associative array as the second argument:

```php
public function testCreatePost(): void
{
    $response = $this->graphQL(/** @lang GraphQL */ '
        mutation CreatePost($title: String!) {
            createPost(title: $title) {
                id
            }
        }
    ', [
        'title' => 'Automatic testing proven to reduce stress levels in developers'
    ]);
}
```

## Assertions

Now that we know how to query our server in tests, we need to make sure the
returned results match our expectations.

The returned `TestResponse` conveniently offers assertions that work quite
well with the JSON data returned by GraphQL.

The `assertJson` method asserts that the response is a superset of the given JSON.

```php
public function testQueriesPosts(): void
{
    $post = factory(Post::class)->create();

    $this->graphQL(/** @lang GraphQL */ '
    {
        posts {
            id
            title
        }
    }
    ')->assertJson([
        'data' => [
            'posts' => [
                [
                    'id' => $post->id,
                    'title' => $post->title,
                ]
            ]
        ]
    ]);
}
```

You can also extract data from the response and use it within any assertion.

```php
public function testOrdersUsersByName(): void
{
    factory(User::class)->create(['name' => 'Oliver']);
    factory(User::class)->create(['name' => 'Chris']);
    factory(User::class)->create(['name' => 'Benedikt']);

    $response = $this->graphQL(/** @lang GraphQL */ '
    {
        users(orderBy: "name") {
            name
        }
    }
    ');

    $names = $response->json("data.*.name");

    $this->assertSame(
        [
            'Benedikt',
            'Chris',
            'Oliver',
        ],
        $names
    );
}
```

### TestResponse Assertion Mixins

Lighthouse conveniently provides additional assertions as mixins to the `TestResponse` class.
Make sure to generate the latest [IDE-helper file](/_ide_helper.php) to get proper autocompletion:

```bash
php artisan lighthouse:ide-helper
```

The provided assertions are prefixed with `assertGraphQL` for easy discovery.
They offer useful shortcuts to common testing tasks.
For example, you might want to ensure that validation works properly:

```php
$this
    ->graphQL(/** @lang GraphQL */ '
    mutation {
        createUser(email: "invalid email")
    }
    ')
    ->assertGraphQLValidationKeys(['email']);
```

## Simulating File Uploads

Lighthouse allows you to [upload files](../digging-deeper/file-uploads.md) through GraphQL.

Since multipart form requests are tricky to construct, you can just use the `multipartGraphQL`
helper method.

```php
$operations = [
    'operationName' => 'upload',
    'query' => 'mutation upload ($file: Upload!) {
                    upload (file: $file)
                }',
    'variables' => [
        'file' => null,
    ],
];

$map = [
    '0' => ['variables.file'],
];

$file = [
    '0' => UploadedFile::fake()->create('test.pdf', 500),
];

$this->multipartGraphQL($operations, $map, $file);
```

## Introspection

If you create or manipulate parts of your schema programmatically, you might
want to test that. You can use introspection to query your final schema in tests.

Lighthouse uses the introspection query from [`\GraphQL\Type\Introspection::getIntrospectionQuery()`](https://github.com/webonyx/graphql-php/blob/master/src/Type/Introspection.php).

The `introspect()` helper method runs the full introspection query against your schema.

```php
$introspectionResult = $this->introspect();
```

Most often, you will want to look for a specific named type.

```php
$generatedType = $this->introspectType('Generated');
// Ensure the type is present and matches a certain definition
$this->assertSame(
    [], // Adjust accordingly
    $generatedType
);
```

You can also introspect client directives.

```php
$customDirective = $this->introspectDirective('custom');
```

## Defer

When sending requests with field containing `@defer`, use the `streamGraphQL()` helper.
It automatically captures the full streamed response and provides you the returned chunks.

```php
$chunks = $this->streamGraphQL(/** @lang GraphQL */ '
{
    now
    later @defer
}
');

$this->assertSame(
    [
        [
            'data' => [
                'now' => 'some value',
                'later' => null,
            ],
        ],
        [
            'later' => [
                'data' => 'another value',
            ],
        ],
    ],
    $chunks
);
```

You can also set up the in-memory stream manually:

```php
$this->setUpDeferStream();
```

## Lumen

Because the `TestResponse` class is not available in Lumen, you must use a different
test trait:

```diff
<?php

namespace Tests;

+use Nuwave\Lighthouse\Testing\MakesGraphQLRequestsLumen;

abstract class TestCase extends Laravel\Lumen\Testing\TestCase
{
+   use MakesGraphQLRequestsLumen;
}
```

All the test helpers are called the same as in `MakesGraphQLRequest`, the only
difference is that they return `$this` instead of a `TestResponse`.
Assertions work differently as a result:

```php
public function testHelloWorld(): void
{
    $this->graphQL(/** @lang GraphQL */ '
    {
        hello
    }
    ')->seeJson([
        'data' => [
            'hello' => 'world'
        ]
    ])->seeHeader('SomeHeader', 'value');
}
```
