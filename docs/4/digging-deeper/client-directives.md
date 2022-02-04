# Client Directives

Client directives allow clients to change the behaviour of query execution.

> Client directives must not be used within your schema definition.

The [GraphQL specification](https://graphql.github.io/graphql-spec/June2018/#sec-Type-System.Directives)
mentions two client directives: [@skip](#skip) and [@include](#include).
Both are built-in to Lighthouse and work out-of-the-box.

## @skip

This directive is part of the [GraphQL spec](https://graphql.github.io/graphql-spec/June2018/#sec--include)
and is built-in to Lighthouse.

The [@skip](#skip) directive may be provided for fields, fragment spreads, and inline fragments, and allows for conditional
exclusion during execution as described by the `if` argument.

```graphql
directive @skip(
  """
  If the value passed into the if field is true the field this
  is decorating will not be included in the query response.
  """
  if: Boolean!
) on FIELD | FRAGMENT_SPREAD | INLINE_FRAGMENT
```

In this example experimentalField will only be queried if the variable \$someTest has the value `false`.

```graphql
query myQuery($someTest: Boolean) {
  experimentalField @skip(if: $someTest)
}
```

## @include

This directive is part of the [GraphQL spec](https://graphql.github.io/graphql-spec/June2018/#sec--include)
and is built-in to Lighthouse.

The [@include](#include) directive may be provided for fields, fragment spreads, and inline fragments,
and allows for conditional inclusion during execution as described by the `if` argument.

```graphql
directive @include(
  """
  If the "if" value is true the field this is connected with will be included in the query response.
  Otherwise it will not.
  """
  if: Boolean
) on FIELD | FRAGMENT_SPREAD | INLINE_FRAGMENT
```

In this example experimentalField will only be queried if the variable \$someTest has the value true

```graphql
query myQuery($someTest: Boolean) {
  experimentalField @include(if: $someTest)
}
```

## Custom Client Directives

You can implement your own client directives.
First, add a definition of your directive to your schema.

```graphql
"A description of what this directive does."
directive @example(
  "Client directives can have arguments too!"
  someArg: String
) on FIELD
```

By itself, a custom client directive does not do anything.
Lighthouse provides a class to retrieve information about where client directives
were placed in the query and what arguments were given to them.

```php
$clientDirective = new \Nuwave\Lighthouse\ClientDirectives\ClientDirective('example');
```

The most common use case for a client directive is to place it on a field. There is a caveat
to working with this that is unintuitive at first: There might be multiple nodes referencing a single
field, and each of those may or may not have the client directive set, with possibly different arguments.

The following example illustrates how a field `foo` can be referenced three times with different
configurations of a client directive:

```graphql
{
  foo
  fooBar: foo @example
  ... on Query {
    foo @example(bar: "baz")
  }
}
```

You can get all arguments for every node that is referencing the field you are currently
resolving, passing the fourth [resolver argument `ResolveInfo $resolveInfo`](../api-reference/resolvers.md#resolver-function-signature):

```php
$arguments = $clientDirective->forField($resolveInfo);
```

The resulting `$arguments` will be an array of 1 to n values, n being the amount of nodes.
For the example query above, it will look like this:

```php
[
    null, # No directive on the first reference
    [], # Directive present, but no arguments given
    ['bar' => 'baz'], # Present with arguments
]
```

You are then free to implement whatever logic on top of that. Some client directives may require
only one field node to have it set, whereas others might require all of them to have the same configuration.

> There are other locations where client directives may be used on: https://spec.graphql.org/draft/#ExecutableDirectiveLocation
> You can add a PR to Lighthouse if you need them.
