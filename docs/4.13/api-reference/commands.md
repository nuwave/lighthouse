# Artisan Commands

Lighthouse provides some convenient artisan commands. All of them
are namespaced under `lighthouse`.

## clear-cache

Clear the cache for the GraphQL AST.

    php artisan lighthouse:clear-cache

## directive

Create a class for a GraphQL directive.

    php artisan lighthouse:directive

## ide-helper

Create IDE helper files to improve type checking and autocompletion.
This will improve the editing experience when using schema directives, as well
as the `TestResponse` mixins.

    php artisan lighthouse:ide-helper

A great way to keep this is up date to with your current version of Lighthouse
is to add it to your `composer.json`:

```json
"scripts": {
    ...
    "post-update-cmd": [
        "php artisan lighthouse:ide-helper"
    ],
```

## interface

Create a class for a GraphQL interface type.

    php artisan lighthouse:interface <name>

## mutation

Create a class for a single field on the root Mutation type.

    php artisan lighthouse:mutation <name>

## print-schema

Compile the final GraphQL schema and print the result.

    php artisan lighthouse:print-schema

This can be quite useful, as the root `.graphql` files do not necessarily
contains the whole schema. Schema imports, native PHP types and schema manipulation
may influence the final schema.

Use the `-W` / `--write` option to output the schema to the default file storage
(usually `storage/app`) as `lighthouse-schema.graphql`.

You can output your schema in JSON format by using the `--json` flag.

## query

Create a class for a single field on the root Query type.

    php artisan lighthouse:query <name>

## scalar

Create a class for a GraphQL scalar type.

    php artisan lighthouse:scalar <name>

## subscription

Create a class for a single field on the root Subscription type.

    php artisan lighthouse:subscription <name>

## union

Create a class for a GraphQL union type.

    php artisan lighthouse:union <name>

## validate-schema

Validate the GraphQL schema definition.

    php artisan lighthouse:validate-schema
