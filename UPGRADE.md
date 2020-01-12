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
- profile: User! @middlware(checks: ["auth"])
+ profile: User! @guard
}
```

Other functionality can be replaced by a custom [`FieldMiddleware`](docs/master/custom-directives/field-directives.md#fieldmiddleware)
directive. Just like Laravel Middleware, it can wrap around individual field resolvers.

### `@orderBy` argument renamed to `column`

The argument to specify the column to order by when using `@orderBy` was renamed
to `column` to match the `@whereConstraints` directive.

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
