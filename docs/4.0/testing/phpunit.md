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
    /** @var \Illuminate\Foundation\Testing\TestResponse $response */
    $response = $this->graphQL('
    {
        posts {
            id
            title
        }
    }
    ');
}
```

If you want to use variables within your query, you can use the `postGraphQL` function instead.

```php
public function testCreatePost(): void
{
    /** @var \Illuminate\Foundation\Testing\TestResponse $response */
    $response = $this->postGraphQL([
        'query' => '
            mutation CreatePost($title: String!) {
                createPost(title: $title) {
                    id
                }
            }
        ',
        'variables' => [
            'title' => 'Automatic testing proven to reduce stress levels in developers'
        ],
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

    $this->graphQL('
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

    $response = $this->graphQL('
    {
        users(orderBy: "name") {
            name
        }
    }
    ');

    $names = $response->json("data.*.name");

    $this->assertSame(
        [
            'Benedikt'
            'Chris',
            'Oliver',
        ],
        $names
    );
}
```

## Simulating File Uploads

Lighthouse allows you to [upload files](../digging-deeper/file-uploads.md) through GraphQL.

Since multipart form requests are tricky to construct, you can just use the `multipartGraphQL`
helper method.

```php
$this->multipartGraphQL(
    [
        'operations' => /* @lang JSON */
            '
            {
                "query": "mutation Upload($file: Upload!) { upload(file: $file) }",
                "variables": {
                    "file": null
                }
            }
        ',
        'map' => /* @lang JSON */
            '
            {
                "0": ["variables.file"]
            }
        ',
    ],
    [
        '0' => UploadedFile::fake()->create('image.jpg', 500),
    ]
)
```
