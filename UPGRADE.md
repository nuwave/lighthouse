# Upgrade guide

This document provides guidance for upgrading between major versions of Lighthouse.

## General tips

The configuration options often change between major versions.
Compare your `lighthouse.php` against the latest [default configuration](src/lighthouse.php).

## v4 to v5

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

### `@modelClass` and `@model` changed 

The `@model` directive was repurposed to take the place of `@modelClass`, see
https://github.com/nuwave/lighthouse/pull/974 for details.
You can adapt to this change in two refactoring steps that must be done in order:

1. Rename all usages of `@model` to `@node`

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
