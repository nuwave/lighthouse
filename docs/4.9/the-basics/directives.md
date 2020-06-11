# Directives

Assuming you read through the previous chapters, you should be familiar with the basics
of schema definition by now.

You might have seen some funky extra bits in the schema definitions such as `@paginate`,
`@rules` or `@hasMany`. Those are called _directives_ and are the primary way
to add functionality to your GraphQL schema.

## Definition

Directives always begin with an `@` symbol, followed by a unique name. They may be used
at specified parts of the GraphQL schema.

This example directive `@upperCase` may be used on field definitions to UPPERCASE the result.

```graphql
directive @upperCase on FIELD_DEFINITION

type Query {
  hello: String @upperCase
}
```

Directives may also define arguments to enable a more flexible use, and they can
be used in multiple places, depending on the [specified directive location](https://facebook.github.io/graphql/June2018/#DirectiveLocation).

```graphql
directive @append(text: String) on FIELD_DEFINITION | ARGUMENT_DEFINITION

type Query {
  sayFriendly: String @append(text: ", please.")
  shout(phrase: String @append(text: "!")): String
}
```

## Usage

Lighthouse provides a plethora of built-in schema directives that are ready to
be consumed and can simply be used from within the schema.

The following example is quite dense, but it should give you an idea of what
directives are capable of.

```graphql
type Query {
  "Return a list of posts"
  posts(
    "Place an exact match filter (=) on the data"
    postedAt: Date @eq
    "Show only posts that match one of the given topics"
    topics: [String!] @in(key: "topic")
    "Search by title"
    title: String @where(operator: "%LIKE%")
  ): [Post!]!
    # Resolve as a paginated list
    @paginate
    # Require authentication
    @guard(with: "api")
}
```

Explore the docs to find out more or look into the [directives API reference](../api-reference/directives.md)
for a complete list of all available directives.

Implementing your own directives is a great way to add reusable functionality to your schema,
learn how you can [implement your own directives](../custom-directives/getting-started.md).
