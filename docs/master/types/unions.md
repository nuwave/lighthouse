# Unions
A Union is an abstract type that simply enumerates other Object Types.
They are similar to interfaces in that they can return different types, but they can not
have fields defined.

```graphql
union Person
  = User
  | Employee

type User {
  id: ID!
}

type Employee {
  employeeId: ID!
}
```

Just like Interfaces, you need a way to determine the concrete Object Type for a Union,
based on the resolved value. If the default type resolver does not work for you, define your
own using `php artisan lighthouse:union <Union name>`.
It is automatically put in the default namespace where Lighthouse can discover it by itself.

Read more about them in the [GraphQL Reference](https://graphql.org/learn/schema/#union-types) and the
[docs for graphql-php](http://webonyx.github.io/graphql-php/type-system/unions/)
