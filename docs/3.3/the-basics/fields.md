# Fields

The entrypoints to any GraphQL API are the fields of the root types `Query`, `Mutation` and `Subscription`.

_Every_ field has a function associated with it that is called when the field
is requested as part of a query. This function is called a **resolver**.

The following section will teach you how to define a resolver for your fields
and how you can utilize Lighthouse's built-in resolvers.

## Resolving fields

As is the tradition of our people, this section will teach you how to say "hello world!" through Lighthouse.

### Schema definition

The following schema defines a simple field called `hello` that returns a `String`.

```graphql
type Query {
  hello: String!
}
```

You need to implement the actual resolver next.

### Defining resolvers

By default, Lighthouse looks for a class with the capitalized name of the field in `App\GraphQL\Queries`
or `App\GraphQL\Mutations` and calls its `resolve` function with [the usual resolver arguments](../api-reference/resolvers.md#resolver-function-signature).

In this case, our field is called `hello` so we need to define our class as follows:

```php
<?php

namespace App\GraphQL\Queries;

class Hello
{
    public static function resolve(): string
    {
        return 'world!';
    }
}
```

The easiest way to create such a class is to use the built in `artisan` commands
`lighthouse:query` and `lighthouse:mutation`. They both take a single argument:
the name of the field you want to generate.

For example, this is how you generate a class for the field `hello`:

    php artisan lighthouse:query Hello

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

### Fields with arguments

As we learned, _every_ field has a resolver function associated with it.
Just like functions, fields can take arguments to control their behaviour.

Let's construct a query that greets the user. We add a required argument `name`
that is used to construct the greeting.

```graphql
type Query {
  greet(name: String!): String
}
```

A minimal implementation of the field could look something like this.
The skeleton for this class can be created using `php artisan lighthouse:query Greet`.

The second argument of the resolver function is an associative array of the
arguments that are passed to the query.

```php
<?php

namespace App\GraphQL\Queries;

class Greet
{
    public function resolve($rootValue, array $args): string
    {
        return "Hello, {$args['name']}!";
    }
}
```

We can call this query, passing a `name` of our choosing.

```graphql
{
  greet(name: "Foo")
}
```

And receive a friendly greeting.

```json
{
  "data": {
    "greet": "Hello, Foo!"
  }
}
```

If we don't want to require the user to pass an argument, we can modify our schema
and make the `name` optional and provide a default value.

```graphql
type Query {
  greet(name: String = "you"): String
}
```

Now we can use our query like this:

```graphql
{
  greet
}
```

```json
{
  "data": {
    "greet": "Hello, you!"
  }
}
```

### Resolving non-root fields

As mentioned, every field in the schema has a resolver - but what
about fields that are not on one of the root types?

```graphql
type Query {
  user: User!
}

type User {
  id: ID!
  name: String!
  email: String
}
```

Let's play through what happens when the client send's the following query:

```graphql
{
  user {
    id
    name
  }
}
```

First, the resolver for `user` will be called. Let's suppose it returns an instance
of `App\Model\User`.

Next, the field sub-selection will be resolved - the two requested fields are `id` and `name`.
Since we resolved the User already in the parent field, we do not want to fetch it again
to get it's attributes.

Conveniently, the first argument of each resolver is the return value of the parent
field, in this case a User model.

A naive implementation of a resolver for `id` might look like this:

```php
<?php

use App\Models\User;

function resolveUserId(User $user): string
{
    return $user->id;
}
```

Writing out each such resolver would be pretty repetitive.
We can utilize the fourth and final resolver argument `ResolveInfo`,
which will give us access to the requested field name,
to dynamically access the matching property.

```php
<?php

use App\Models\User;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

function resolveUserAttribute(User $user, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
{
    return $user->{$resolveInfo->fieldName};
}
```

Fortunately, the underlying GraphQL implementation already provides [a sensible default resolver](http://webonyx.github.io/graphql-php/data-fetching/#default-field-resolver),
that plays quite nicely with the data you would typically return from
a root resolver, e.g. `Eloquent` models or associative arrays.

This means that in most cases, you will only have to provide resolvers for the
root fields and make sure they return data in the proper shape.

If you need to implement custom resolvers for fields that are not on one of the
root types `Query` or `Mutation`, you can use either the
[@field](../api-reference/directives.md#field) or [@method](../api-reference/directives.md#method) directive.

You may also [change the default resolver](../guides/plugin-development.md#change-the-default-resolver) if you need.

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

### Adding query constraints

Lighthouse provides built-in directives to enhance your queries by giving
additional query capabilities to the client.

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

Per convention, a GraphQL _Query_ is not allowed to change data.
You will need to define a _Mutation_ for that.
Mutations look just like queries, but only they can create, update or delete data.

The following examples will show you how to make changes to a single model.
If you need to save multiple related models at once, look into [Mutating Relationships](../guides/relationships.md#mutating-relationships).

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

Simply call it with the ID of the user you want to delete.

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

## Subscribe to data

Lighthouse allows you to serve GraphQL subscriptions. Compared to queries and
mutations, a more elaborate setup is required.

[Read more about how to set up subscriptions](../extensions/subscriptions.md)
