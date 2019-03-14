# Interfaces
The GraphQL `interface` type is similar to a PHP `Interface`.
It defines a set of common fields that all implementing types must also provide.
A common use-case for interfaces with a Laravel project would be polymorphic relationships.

```graphql
interface Named {
  name: String!
}
```

Object types can implement that interface, given that they provide all its fields.

```graphql
type User implements Named {
  id: ID!
  name: String!
}
```

The following definition would be invalid.

```graphql
type User implements Named {
  id: ID!
}
```

Interfaces need a way of determining which concrete Object Type is returned by a
particular query. Lighthouse provides a default type resolver that works by calling
`class_basename($value)` on the value returned by the resolver.

You can also provide a custom type resolver. Run `php artisan lighthouse:interface <Interface name>` to create
a custom interface class. It is automatically put in the default namespace where Lighthouse can discover it by itself.

Read more about them in the [GraphQL Reference](https://graphql.org/learn/schema/#interfaces) and the
[docs for graphql-php](http://webonyx.github.io/graphql-php/type-system/interfaces/)
