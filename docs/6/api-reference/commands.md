# Artisan Commands

Lighthouse provides some convenient artisan commands.
All of them are namespaced under `lighthouse`.

## cache

Compile the GraphQL schema and cache it.

```sh
php artisan lighthouse:cache
```

## clear-cache

Clear the GraphQL schema cache.

```sh
php artisan lighthouse:clear-cache
```

## directive

Create a class for a custom schema directive.

```sh
php artisan lighthouse:directive
```

Use the `--type`, `--field` and `--argument` options to define where your directive can be used.

## field

Create a resolver class for a field on a non-root type.

```sh
php artisan lighthouse:field <parent>.<field>
```

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

If the generated definitions conflict with those provided by your IDE, try `--omit-built-in` to avoid redefining
built-in directives such as `@deprecated`.

## interface

Create a type resolver class for a GraphQL interface type.

```sh
php artisan lighthouse:interface <name>
```

## mutation

Create a resolver class for a single field on the root Mutation type.

```sh
php artisan lighthouse:mutation <name>
```

Use the option `--full` to include the seldom needed resolver arguments `$context` and `$resolveInfo`.

## print-schema

Compile the GraphQL schema and print the result.

```sh
php artisan lighthouse:print-schema
```

This can be quite useful, as the root `.graphql` files do not necessarily contain the whole schema.
Schema imports, native PHP types and schema manipulation may influence the final schema.

Use the `-W` / `--write` option to output the schema to the default file storage (usually `storage/app`) as `lighthouse-schema.graphql`.
You can output your schema in JSON format by using the `--json` flag.

The `--federation` option should be used to produce a schema file suitable for [Apollo Federation](https://www.apollographql.com/docs/federation).

## query

Create a resolver class for a single field on the root Query type.

```sh
php artisan lighthouse:query <name>
```

Use the option `--full` to include the seldom needed resolver arguments `$context` and `$resolveInfo`.

## scalar

Create a class for a GraphQL scalar type.

```sh
php artisan lighthouse:scalar <name>
```

## subscription

Create a resolver class for a single field on the root Subscription type.

```sh
php artisan lighthouse:subscription <name>
```

## union

Create a type resolver class for a GraphQL union type.

```sh
php artisan lighthouse:union <name>
```

## validate-schema

Validate the GraphQL schema definition.

```sh
php artisan lighthouse:validate-schema
```
