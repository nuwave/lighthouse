# Upgrade guide

This document provides guidance for upgrading between major versions of Lighthouse.

## General tips

The configuration options often change between major versions.
Compare your `lighthouse.php` against the latest [default configuration](src/lighthouse.php).

## v4 to v5

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
