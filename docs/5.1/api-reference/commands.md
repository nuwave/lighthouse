# Artisan Commands

Lighthouse provides some convenient artisan commands. All of them
are namespaced under `lighthouse`.

## cache

Compile the GraphQL schema and cache it.

    php artisan lighthouse:cache

## clear-cache

Clear the GraphQL schema cache.

    php artisan lighthouse:clear-cache

## directive

Create a class for a custom schema directive.

    php artisan lighthouse:directive

Use the `--type`, `--field` and `--argument` options to create type, field and
argument directives, respectively. The command will then ask you which
interfaces the directive should implement and add the required method stubs and
imports for you.

## ide-helper

Create IDE helper files to improve type checking and autocompletion.

    php artisan lighthouse:ide-helper

This will create the following files:

- `schema-directives.graphql`: Schema definitions for directives you can use in your schema
- `programmatic-types.graphql`: Schema definitions for programmatically registered types, if you have any
- `_lighthouse_ide_helper.php`: Class definitions for some magical PHP, such as the `TestResponse` mixin

A great way to keep up to date with your current version of Lighthouse
is to add this script to your `composer.json`:

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

Use the option `--full` to include the seldom needed resolver arguments `$context` and `$resolveInfo`.

## print-schema

Compile the GraphQL schema and print the result.

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

Use the option `--full` to include the seldom needed resolver arguments `$context` and `$resolveInfo`.

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
