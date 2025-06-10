# Testing with PHPUnit

Lighthouse makes it easy to add automated tests through [PHPUnit](https://phpunit.de).

## Setup

Lighthouse offers some useful test helpers.
Keep in mind they only work when your test class extends `Illuminate\Foundation\Testing\TestCase`.

The `MakesGraphQLRequests` trait make it easy to call your API:

```diff
+use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
+   use MakesGraphQLRequests;
}
```

Enabling the schema cache speeds up your tests.
To ensure the schema is fresh before running tests, add the `RefreshesSchemaCache` trait:

```diff
+use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
+   use RefreshesSchemaCache;
}
```

If you want to test subscriptions, add the `TestsSubscriptions` trait:

```diff
+use Nuwave\Lighthouse\Testing\TestsSubscriptions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
+   use TestsSubscriptions;
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
        mutation ($title: String!) {
            createPost(title: $title) {
                id
            }
        }
    ', [
        'title' => 'Automatic testing proven to reduce stress levels in developers'
    ]);
}
```

You can run a subscription query the same way.

```php
public function testPostsSubscription(): void
{
    $response = $this->graphQL(/** @lang GraphQL */ '
    {
        subscription {
            onPostCreated {
                title
            }
        }
    }
    ');
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
                ],
            ],
        ],
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

### Subscription Assertions

#### Testing Authorization

Once you do your subscription query and get a `TestResponse $response`, you can run the following assertions on it.

If you want to make sure the current user is authorized to join a subscription:

```php
$response->assertGraphQLSubscriptionAuthorized($this);
```

If you want to make sure the current user is NOT authorized to join a subscription:

```php
$response->assertGraphQLSubscriptionNotAuthorized($this);
```

#### Testing Broadcast

Once you do your subscription query and get a `TestResponse $response`, you can run the following assertions on it.

To assert the subscription actually received some broadcasts:

```
// any other way to broadcast would also work
Subscription::broadcast('postUpdated', ['title' => 'foo']);
Subscription::broadcast('postUpdated', ['title' => 'bar']);

$response->assertGraphQLBroadcasted([
    ['title' => 'foo'],
    ['title' => 'bar'],
]);
```

To assert the subscription received no broadcasts:

```
// nothing that causes a broadcast to this channel

$response->assertGraphQLNotBroadcasted();
```

If you need more control over your broadcast assertion you can use `graphQLSubscriptionMock` which returns a [spy](http://docs.mockery.io/en/latest/reference/spies.html) and `graphQLSubscriptionChannelName`

```php
$spy = $response->graphQLSubscriptionMock($this);

$spy->shouldNotHaveReceived('broadcast');
// or
$spy->shouldNotHaveReceived('broadcast', function (Subscriber $subscriber, $broadcastedData) use ($response): bool {
    $channel = $response->graphQLSubscriptionChannelName();
    return $channel !== $subscriber->channel;
});
```

### TestResponse Assertion Mixins

Lighthouse conveniently provides additional assertions as mixins to the `TestResponse` class.
Make sure to [generate the latest IDE-helper file](../api-reference/commands.md#ide-helper) to get proper autocompletion:

```shell
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

## Testing Errors

Depending on your debug and error handling configuration, Lighthouse catches most if
not all errors produced within queries and includes them within the result.

One way to test for errors is to examine the `TestResponse`, either by looking
at the JSON response manually or by using the provided [assertion mixins](#testresponse-assertion-mixins)
such as `assertGraphQLErrorMessage()`:

```php
$this
    ->graphQL(/** @lang GraphQL */ '
    mutation {
        shouldTriggerSomeError
    }
    ')
    ->assertGraphQLErrorMessage($expectedMessage);
```

Another way is to leverage PHPUnit's built-in methods such as `expectException()`.
You must disable Lighthouse's error handling with `rethrowGraphQLErrors()` to ensure errors reach your test:

```php
$this->rethrowGraphQLErrors();

$this->expectException(SomethingWentWrongException::class);
$this->graphQL(/** @lang GraphQL */ '
{
    oops
}
');
```

## Simulating File Uploads

Lighthouse allows you to [upload files](../digging-deeper/file-uploads.md) through GraphQL.

Since multipart form requests are tricky to construct, you can just use the `multipartGraphQL`
helper method.

```php
$operations = [
    'query' => /** @lang GraphQL */ '
        mutation ($file: Upload!) {
            upload(file: $file)
        }
    ',
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
            'hello' => 'world',
        ],
    ])->seeHeader('SomeHeader', 'value');
}
```
