# Scalars
Scalar types are the most basic elements of a GraphQL schema. There are a
few built in scalars, such as `String` or `Int`.

Lighthouse provides some scalars that work well with Laravel out of the box, you can find
them in the [default schema](../getting-started/installation.md#publish-the-default-schema).

Define your own scalar types by running `php artisan lighthouse:scalar <Scalar name>`
and including it in your schema. Lighthouse will look for Scalar types in a configurable
default namespace.

```graphql
scalar ZipCode

type User {
  zipCode: ZipCode
}
```

You can also use third-party scalars, such as those provided by [mll-lab/graphql-php-scalars](https://github.com/mll-lab/graphql-php-scalars).
Just `composer require` your package of choice and add a scalar definition to your schema.
Use the [@scalar](../api-reference/directives.md#scalar) directive to point to any fully qualified class name:

```graphql
scalar Email @scalar(class: "MLL\\GraphQLScalars\\Email")
```

[Learn how to implement your own scalar.](https://webonyx.github.io/graphql-php/type-system/scalar-types/)
