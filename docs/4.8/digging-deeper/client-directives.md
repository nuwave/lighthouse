# Client Directives

Client directives allow clients to change the behaviour of query execution.

> Client directives must not be used within your schema definition.

The [GraphQL specification](https://graphql.github.io/graphql-spec/June2018/#sec-Type-System.Directives)
mentions two client directives: [`@skip`](#skip) and [`@include`](#include).
Both are built-in to Lighthouse and work out-of-the-box.

## Custom Client Directives

You can implement your own client directives.
Add a definition to your schema to show it in introspection.

```graphql
"A description of what this directive does."
directive @example(
  "Client directives can have arguments too!"
  someArg: String
) on FIELD
```

By itself, a custom client directive does not do anything.
Use the fourth [resolver argument `ResolveInfo $resolveInfo`](../api-reference/resolvers.md#resolver-function-signature)
to check the presence and/or value of a client directive and act on it.

## @skip

This directive is part of the [GraphQL spec](https://graphql.github.io/graphql-spec/June2018/#sec--include)
and is built-in to Lighthouse.

The `@skip` directive may be provided for fields, fragment spreads, and inline fragments, and allows for conditional
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

The `@include` directive may be provided for fields, fragment spreads, and inline fragments,
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
