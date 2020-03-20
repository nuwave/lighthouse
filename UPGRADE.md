# Upgrade guide

This document provides guidance for upgrading between major versions of Lighthouse.

## General tips

The configuration options often change between major versions.
Compare your `lighthouse.php` against the latest [default configuration](src/lighthouse.php).

## v4 to v5

### Replace @middleware with @guard and specialized FieldMiddleware

The `@middleware` directive has been removed, as it violates the boundary between HTTP and GraphQL
request handling.

Authentication is one of most common use cases for `@middleware`. You can now use
the [`@guard`](docs/master/api-reference/directives.md#guard) on selected fields.

```diff
type Query {
-   profile: User! @middlware(checks: ["auth"])
+   profile: User! @guard
}
```

Other functionality can be replaced by a custom [`FieldMiddleware`](docs/master/custom-directives/field-directives.md#fieldmiddleware)
directive. Just like Laravel Middleware, it can wrap around individual field resolvers.

### `@orderBy` argument renamed to `column`

The argument to specify the column to order by when using `@orderBy` was renamed
to `column` to match the `@whereConditions` directive.

Client queries will have to be changed like this:

```diff
{
    posts (
        orderBy: [
            {
-               field: POSTED_AT
+               column: POSTED_AT
                order: ASC
            }
        ]
    ) {
        title
    }
}
```

If you absolutely cannot break your clients, you can re-implement `@orderBy` in your
project - it is a relatively simple `ArgManipulator` directive.

### `@modelClass` and `@model` changed 

The `@model` directive was repurposed to take the place of `@modelClass`. As a replacement
for the current functionality of `@model`, the new `@node` directive was added,
see https://github.com/nuwave/lighthouse/pull/974 for details.

You can adapt to this change in two refactoring steps that must be done in order:

1. Rename all usages of `@model` to `@node`, e.g.:

    ```diff
    -type User @model {
    +type User @node {
        id: ID! @globalId
    }
    ```

2. Rename all usages of `@modelClass` to `@model`, e.g.

    ```diff
    -type PaginatedPost @modelClass(class: "\\App\\Post") {
    +type PaginatedPost @model(class: "\\App\\Post") {
        id: ID!
    }
    ```

### Replace `@bcrypt` with `@hash`

The new `@hash` directive is also used for password hashing, but respects the
configuration settings of your Laravel project.

```diff
type Mutation {
    createUser(
        name: String!
-       password: String! @bcrypt
+       password: String! @hash
    ): User!
}
```

### `@method` passes down just ordered arguments

Instead of passing down the usual resolver arguments, the `@method` directive will
now pass just the arguments given to a field. This behaviour could previously be
enabled through the `passOrdered` option, which is now removed.

```graphql
type User {
    purchasedItemsCount(
        year: Int!
        includeReturns: Boolean
    ): Int @method
}
```

The method will have to change like this:

```diff
-public function purchasedItemsCount($root, array $args)
+public function purchasedItemsCount(int $year, ?bool $includeReturns)
```
