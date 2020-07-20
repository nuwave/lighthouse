# Migrating to Lighthouse

This section contains advice on how you can migrate existing
API projects to Lighthouse.

## From other GraphQL servers

### Schema definition

The most important thing to get you started using Lighthouse will
be a schema that is written using GraphQL Schema Definition Language.

If you already have a server with another library up and running, you
can use introspection to retrieve this schema and save it to a file.

A simple tool that is also generally useful is [graphql-cli](https://github.com/graphql-cli/graphql-cli).

    npm install -g graphql-cli
    graphql init
    graphql get-schema --endpoint=example.com/graphql --output=schema.graphql

Type definitions that previously done through code can mostly be deduced from
the schema. Sometimes, additional annotations or a PHP implementation is required.
[How to define types](../the-basics/types.md)

### Resolver logic

If you are coming from libraries such as [Folkloreatelier/laravel-graphql](https://github.com/Folkloreatelier/laravel-graphql),
[rebing/laravel-graphql](https://github.com/rebing/graphql-laravel) or any other library that
is originally based upon [webonyx/graphql-php](https://github.com/webonyx/graphql-php),
you should be able to reuse much of your existing code.

You can also register your existing types within Lighthouse's type registry, so you
won't have to rewrite them in SDL: [Use native PHP types](../guides/native-php-types.md).

Resolver functions share the same [common signature](../api-reference/resolvers.md#resolver-function-signature),
so you should be able to reuse any logic you have written for Queries/Mutations.

Lighthouse simplifies many common tasks, such as [basic CRUD operations](../the-basics/fields.md),
[eager loading relationships](../guides/relationships.md#querying-relationships),
[pagination](../api-reference/directives.md#paginate) or [validation](../guides/validation.md).
