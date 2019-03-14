# Enums
Enums are types with a restricted set of values (similar to `enum` found in database migrations).
They are defined as a list of `UPPERCASE` string keys. You can define the actual values through
the [@enum](../api-reference/directives.md#enum) directive.


```graphql
enum EmploymentStatus {
  INTERN @enum(value: 0)
  EMPLOYEE @enum(value: 1)
  TERMINATED @enum(value: 2)
}
```

Now we can use the enum as part of our schema.

```graphql
type Employee {
  id: ID!
  name: String
  status: EmploymentStatus!
}

type Query {
  employees: [Employee!]! @all
}
```

In this example, the underlying values are actually integers. When the models are retrieved from
the database, the mapping is applied and the integers are converted to the defined string keys.

```php
return [
  ['name' => 'Hans', 'status' => 0],
  ['name' => 'Pamela', 'status' => 1],
  ['name' => 'Gerhard', 'status' => 2],
];
```

Queries now return meaningful names instead of magic numbers.

```graphql
{
  employees {
    name
    status
  }
}
```

```json
{
  "data": {
    "employees": [
      {"name": "Hans", "status": "INTERN"},
      {"name": "Pamela", "status": "EMPLOYEE"},
      {"name": "Gerhard", "status": "TERMINATED"}
    ]
  }
}
```

If the internal value of the enum is the same as the field name, `@enum` can be omitted:

```graphql
enum Role {
  ADMIN
}
```

The PHP internal value of the field `ADMIN` will be `string('ADMIN')`.
