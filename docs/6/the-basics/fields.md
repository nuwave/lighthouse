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
or `App\GraphQL\Mutations` and calls its `__invoke` function with [the usual resolver arguments](../api-reference/resolvers.md#resolver-function-signature).

In this case, our field is a query and is called `hello`, so we need to define our class as follows:

```php
namespace App\GraphQL\Queries;

final class Hello
{
    public function __invoke(): string
    {
        return 'world!';
    }
}
```

The easiest way to create such a class is to use the built-in `artisan` commands
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
namespace App\GraphQL\Queries;

final class Greet
{
    public function __invoke(mixed $root, array $args): string
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

Let's play through what happens when the client sends the following query:

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
to get its attributes.

Conveniently, the first argument of each resolver is the return value of the parent
field, in this case a User model.

A naive implementation of a resolver for `id` might look like this:

```php
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
use App\Models\User;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

function resolveUserAttribute(User $user, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
{
    return $user->{$resolveInfo->fieldName};
}
```

Fortunately, the underlying GraphQL implementation already provides [a sensible default resolver](https://webonyx.github.io/graphql-php/data-fetching/#default-field-resolver),
that plays quite nicely with the data you would typically return from
a root resolver, e.g. `Eloquent` models or associative arrays.

This means that in most cases, you will only have to provide resolvers for the
root fields and make sure they return data in the proper shape.

If you need to implement custom resolvers for fields that are not on one of the
root types `Query` or `Mutation`, you can create a resolver class using the built-in `artisan` command `lighthouse:field`.
For example, this is how you generate a class for the field `name` on type `User`:

    php artisan lighthouse:field User.name

## Resolver precedence

Lighthouse uses the following logic to locate field resolvers.

First, it checks if the field definition in the schema is annotated with a [FieldResolver](../custom-directives/field-directives.md#fieldresolver)
directive. If so, it uses the resolver that is provided by the directive.

The interface [`\Nuwave\Lighthouse\Support\Contracts\ProvidesResolver`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/ProvidesResolver.php)
is expected to provide a resolver in case no resolver directive is defined for a field.
When the field is defined on the root `Subscription` type, the [`Nuwave\Lighthouse\Support\Contracts\ProvidesSubscriptionResolver`](https://github.com/nuwave/lighthouse/tree/master/src/Support/Contracts/ProvidesSubscriptionResolver.php)
interface is used instead.

The default implementation of those interfaces check if there is a class that matches the capitalized name of the field,
and defines a method `__invoke`, in one of the configured default namespaces,
depending on the parent type the field is a part of.

| Parent type    | Namespaces config key                 |
| -------------- | ------------------------------------- |
| `Query`        | `lighthouse.namespaces.queries`       |
| `Mutation`     | `lighthouse.namespaces.mutations`     |
| `Subscription` | `lighthouse.namespaces.subscriptions` |
| Any other type | `lighthouse.namespaces.types`         |

For example, given `lighthouse.namespaces.queries` is defined as `'App\GraphQL\Queries'`
and the field `Query.users`, Lighthouse would expect a resolver class `App\GraphQL\Queries\User`.
If there are multiple namespaces, they are checked in order and the first found class is used.

Non-root types are combined with the configured namespaces.
For example, given `lighthouse.namespaces.types` is defined as `['App\GraphQL\Types', 'MyModule\GraphQL\Types']`
and the field `User.name`, Lighthouse would look for the resolver class `App\GraphQL\Types\User\Name`, then `MyModule\GraphQL\Types\User\Name`.

Resolvers for root fields are mandatory, Lighthouse will throw during schema validation if a root field has no resolver.
Non-root fields fall back to [webonyx's default resolver](https://webonyx.github.io/graphql-php/data-fetching/#default-field-resolver).
