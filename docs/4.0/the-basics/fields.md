# Fields

The entrypoints to any GraphQL API are the fields of the root types `Query`, `Mutation` and `Subscription`.

_Every_ field has a function associated with it that is called when the field
is requested as part of a query. This function is called a **resolver**.

## Hello World

As is the tradition of our people, this section will teach you how to say "hello world!" through Lighthouse.

We start out by defining the simplest possible schema: The root `Query` type
with a single field called `hello` that returns a `String`.

```graphql
type Query {
  hello: String!
}
```

This defines the shape of our data and informs the client what they can expect.
You need to implement the actual resolver next.

By default, Lighthouse looks for a class with the capitalized name of the field in `App\GraphQL\Queries`
or `App\GraphQL\Mutations` and calls its `resolve` function with [the usual resolver arguments](../api-reference/resolvers.md#resolver-function-signature).

In this case, our field is a query and is called `hello`, so we need to define our class as follows:

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

This query will return the following response:

```json
{
  "data": {
    "hello": "world!"
  }
}
```

## Fields with arguments

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

## Resolving non-root fields

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
Since we already resolved the User in the parent field, we do not want to fetch it again
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
We can utilize the fourth and final resolver argument `ResolveInfo`, which will give us access
to the requested field name, to dynamically access the matching property.

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

You may also [change the default resolver](../digging-deeper/extending-lighthouse.md#changing-the-default-resolver) if you need.
