# Fields

To fetch data from your GraphQL endpoint, you need to define resolvers for your fields.
Lighthouse makes this easy by providing easy to use, pre-built resolvers that work
great together with your Eloquent models.

## Hello World!

As is the tradition of our people, this section will teach you how to say "hello world!" through Lighthouse.
The following schema defines a simple field called `hello` that returns a `String`.

```graphql
type Query {
  hello: String!
}
```

You need to implement the actual resolver next. Lighthouse looks for a class with the capitalized name of the
field in `App\Http\GraphQL\Queries` and calls its `resolve` function.

```php
<?php

namespace App\Http\GraphQL\Queries;

class Hello
{
    public static function resolve(): string
    {
        return 'world!';
    }
}
```

Now your schema can be queried.

```graphql
{
  hello
}
```

And will return the following response:

```json
{
  "data": {
    "hello": "world!"
  }
}
```

## Query data

Lighthouse provides many resolvers that are already built-in, so you do not have to define them yourself.
The following is not a comprehensive list of all resolvers but should give you an idea of what you can do.

### Fetch a list of models

Since you are already using Laravel, you might as well use Eloquent to fetch the data for your Query.
Let's say you defined your `User` type like this:

```graphql
type User {
  id: ID!
  name: String!
}
```

Instead of defining your own resolver manually, you can just rely on Lighthouse to build the Query for you.

```graphql
type Query {
  users: [User!]! @all
}
```

The [@all](../api-reference/directives.md#all) directive will assume the name of your model to be the same as
the return type of the Field you are trying to resolve and automatically uses Eloquent to resolve the field.

The following query:

```graphql
{
  users {
    id
    name
  }
}
```

Will return the following result:

```json
{
  "data": {
    "users": [
      { "id": 1, "name": "James Bond" },
      { "id": 2, "name": "Madonna" }
    ]
  }
}
```

### Query with arguments

You may have noticed how every field has to have a resolver function. In many ways, fields are similar to functions.
Just like functions, fields can take arguments to make them more flexible.

The following field allows you to fetch a single User by ID.

```graphql
type Query {
  user(id: ID! @eq): User @find
}
```

You can query this field like this:

```graphql
{
  user(id: 69) {
    name
  }
}
```

And, if found, receive a result like this:

```json
{
  "data": {
    "user": {
      "name": "Chuck Norris"
    }
  }
}
```

## Mutate data

Reading data is all fine and dandy through Queries, but you might want to offer a way to change data, too.
Per convention, a GraphQL _Query_ is not allowed to change data, so you will need to define a _Mutation_ for that.
The only difference between them is their ability to change data, apart from that, they are the same.

### Create

The easiest way to create data on your server is to use the [@create](../api-reference/directives.md#create) in combination
with an existing Laravel model.

```graphql
type Mutation {
  createUser(name: String!): User! @create
}
```

This will take the arguments that the `createUser` field receives and use them to create a new model instance.

```graphql
mutation {
  createUser(name: "Donald") {
    id
    name
  }
}
```

The newly created user is returned as a result:

```json
{
  "data": {
    "createUser": {
      "id": "123",
      "name": "Donald"
    }
  }
}
```

### Update

You can easily add a way to update your data with the [@update](../api-reference/directives.md#update) directive.

```graphql
type Mutation {
  updateUser(id: ID!, name: String): User @update
}
```

Since GraphQL allows you to update just parts of your data, it is best to have all arguments except `id` as optional.

```graphql
mutation {
  updateUser(id: "123", name: "Hillary") {
    id
    name
  }
}
```

```json
{
  "data": {
    "updateUser": {
      "id": "123",
      "name": "Hillary"
    }
  }
}
```

Be aware that while a create operation will always return a result, provided you pass valid data, the update
may fail to find the model you provided and return `null`:

```json
{
  "data": {
    "updateUser": null
  }
}
```

### Delete

Deleting data through your GraphQL API is really easy with the [@delete](../api-reference/directives.md#delete) directive. Dangerously easy.

```graphql
type Mutation {
  deleteUser(id: ID!): User @delete
}
```

Simply call it with the ID if the user you want gone:

```graphql
mutation {
  deleteUser(id: "123") {
    secret
  }
}
```

This mutation will return the deleted object, so you will have a last chance to look at the data. Use it wisely.

```json
{
  "data": {
    "deleteUser": {
      "secret": "Pink is my favorite color!"
    }
  }
}
```

## Custom resolvers

Sometimes, the built-in directives just don't cut it - you need more control!
Lighthouse allows you to implement your own resolver function for fields.

By default, Lighthouse looks for a class with the capitalized name of the field in `App\Http\GraphQL\Queries`
or `App\Http\GraphQL\Mutations` and calls its `resolve` function with [the usual resolver arguments](../api-reference/resolvers.md#resolver-function-signature).
If you stick to that convention, you will not need to specify a directive at all.

For example, the following field:

```graphql
type Query {
  latestPost: Post!
}
```

expects a class like this:

```php
<?php

namespace App\Http\GraphQL\Queries;

use App\Models\Post;
use GraphQL\Type\Definition\ResolveInfo;

class LatestPost
{
    public function resolve($rootValue, array $args, $context, ResolveInfo $resolveInfo): Post
    {
        return Post::orderBy('published_at', 'DESC')->first();
    }
}
```

The easiest way to create such a class is to use the built in artisan commands
`lighthouse:query` and `lighthouse:mutation`. They both take a single argument:
the name of the field you want to generate.

For example, this is how you generate a class for the field `latestPost`:

    php artisan lighthouse:query LatestPost

If you need to implement custom resolvers for fields that are not on one of the
root types `Query` or `Mutation`, you can use the [@field](../api-reference/directives.md#field) directive.
