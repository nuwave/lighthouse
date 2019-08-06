# Client Directives

Client directives are never used in your schema. They allow you to change the 
behaviour of your query results on the client side. There are two client directives included in 
the graphql spec by default. The `@include` and `@skip` directives respectfully. 


## @include

This directive is part of the [GraphQL spec](https://graphql.github.io/graphql-spec/June2018/#sec--include) 
and it should be noted this directive is a client side and should not be included in your schema. 

Only includes a field in response if the value passed into this directive is true. This directive is one of the core 
directives in the GraphQL spec. 

```graphql
directive @include(
    """
    If the "if" value is true the field this is connected with will be included in the query response.
    Otherwise it will not.
    """
    if: Boolean
) on FIELD | FRAGMENT_SPREAD | INLINE_FRAGMENT

```

### Examples

The `@include` directive may be provided for fields, fragment spreads, and inline fragments, 
and allows for conditional inclusion during execution as described by the `if` argument.

In this example experimentalField will only be queried if the variable $someTest has the value true

```graphql
query myQuery($someTest: Boolean) {
  experimentalField @include(if: $someTest)
}
```



## @skip

This directive is part of the [GraphQL spec](https://graphql.github.io/graphql-spec/June2018/#sec--include) 
and it should be noted this directive is a client side directive and should not be included in your schema. 

### Definition
```graphql
directive @skip(
    """
    If the value passed into the if field is true the field this 
    is decorating will not be included in the query response.
    """
    if: Boolean!
) 
on FIELD | FRAGMENT_SPREAD | INLINE_FRAGMENT
```

### Examples

The `@skip` directive may be provided for fields, fragment spreads, and inline fragments, and allows for conditional 
exclusion during execution as described by the if argument.

In this example experimentalField will only be queried if the variable $someTest has the value `false`.

```graphql
query myQuery($someTest: Boolean) {
  experimentalField @skip(if: $someTest)
}
```