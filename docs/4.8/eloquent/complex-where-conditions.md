# Complex Where Conditions

Adding query conditions ad-hoc can be cumbersome and limiting when you require
many different ways to filter query results.
Lighthouse's `WhereConditions` extension can give advanced query capabilities to clients
and allow them to apply complex, dynamic WHERE conditions to queries.

## Setup

**This is an experimental feature and not included in Lighthouse by default.**

Add the service provider to your `config/app.php`

```php
'providers' => [
    \Nuwave\Lighthouse\WhereConditions\WhereConditionsServiceProvider::class,
],
```

Install the dependency [mll-lab/graphql-php-scalars](https://github.com/mll-lab/graphql-php-scalars):

    composer require mll-lab/graphql-php-scalars:^3

## Usage

You can use this feature through a set of schema directives that enhance fields
with advanced filter capabilities.

### @whereConditions

```graphql
"""
Add a dynamically client-controlled WHERE condition to a fields query.
"""
directive @whereConditions(
  """
  Restrict the allowed column names to a well-defined list.
  This improves introspection capabilities and security.
  """
  columns: [String!]
) on ARGUMENT_DEFINITION
```

You can apply this directive on any field that performs an Eloquent query:

```graphql
type Query {
  people(
    where: _ @whereConditions(columns: ["age", "type", "haircolour", "height"])
  ): [Person!]! @all
}

type Person {
  id: ID!
  age: Int!
  height: Int!
  type: String!
  hair_colour: String!
}
```

Lighthouse automatically generates definitions for an `Enum` type and an `Input` type
that are restricted to the defined columns, so you do not have to specify them by hand.
The blank type named `_` will be changed to the actual type.
Here are the types that will be included in the compiled schema:

```graphql
"Dynamic WHERE conditions for the `where` argument on the query `people`."
input PeopleWhereWhereConditions {
  "The column that is used for the condition."
  column: PeopleWhereColumn

  "The operator that is used for the condition."
  operator: SQLOperator = EQ

  "The value that is used for the condition."
  value: Mixed

  "A set of conditions that requires all conditions to match."
  AND: [PeopleWhereWhereConditions!]

  "A set of conditions that requires at least one condition to match."
  OR: [PeopleWhereWhereConditions!]
}

"Allowed column names for the `where` argument on the query `people`."
enum PeopleWhereColumn {
  AGE @enum(value: "age")
  TYPE @enum(value: "type")
  HAIRCOLOUR @enum(value: "haircolour")
  HEIGHT @enum(value: "height")
}
```

When you are not specifying `columns` to allow, clients can specify arbitrary
column names as a `String`. This approach should by taken with care, as it carries
potential performance and security risks and offers little type safety.

A simple query for a person who is exactly 42 years old would look like this:

```graphql
{
  people(where: { column: AGE, operator: EQ, value: 42 }) {
    name
  }
}
```

Note that the operator defaults to `EQ` (`=`) if not given, so you could
also omit it from the previous example and get the same result.

The following query gets actors over age 37 who either have red hair or are at least 150cm:

```graphql
{
  people(
    where: {
      AND: [
        { column: AGE, operator: GT, value: 37 }
        { column: TYPE, value: "Actor" }
        {
          OR: [
            { column: HAIRCOLOUR, value: "red" }
            { column: HEIGHT, operator: GTE, value: 150 }
          ]
        }
      ]
    }
  ) {
    name
  }
}
```

Some operators require passing lists of values - or no value at all. The following
query gets people that have no hair and blue-ish eyes:

```graphql
{
  people(
    where: {
      AND: [
        { column: HAIRCOLOUR, operator: IS_NULL }
        { column: EYES, operator: IN, value: ["blue", "aqua", "turquoise"] }
      ]
    }
  ) {
    name
  }
}
```

Using `null` as argument value does not have any effect on the query.
This query would retrieve all persons without any condition:

```graphql
{
  people(where: null) {
    name
  }
}
```

### @whereHasConditions

```graphql
"""
Allows clients to filter a query based on the existence of a related model, using
a dynamically controlled `WHERE` condition that applies to the relationship.
"""
directive @whereHasConditions(
  """
  The Eloquent relationship that the conditions will be applied to.

  This argument can be omitted if the argument name follows the naming
  convention `has{$RELATION}`. For example, if the Eloquent relationship
  is named `posts`, the argument name must be `hasPosts`.
  """
  relation: String

  """
  Restrict the allowed column names to a well-defined list.
  This improves introspection capabilities and security.
  """
  columns: [String!]
) on ARGUMENT_DEFINITION
```

This directive works very similar to [`@whereConditions`](#whereconditions), except that
the conditions are applied to a relation sub query:

```graphql
type Query {
  people(
    hasRole: _ @whereHasConditions(columns: ["name", "access_level"])
  ): [Person!]! @all
}

type Role {
  name: String!
  access_level: Int
}
```

Again, Lighthouse will auto-generate an `input` and `enum` definition for your query:

```graphql
"Dynamic WHERE conditions for the `hasRole` argument on the query `people`."
input PeopleHasRoleWhereConditions {
  "The column that is used for the condition."
  column: PeopleHasRoleColumn

  "The operator that is used for the condition."
  operator: SQLOperator = EQ

  "The value that is used for the condition."
  value: Mixed

  "A set of conditions that requires all conditions to match."
  AND: [PeopleHasRoleWhereConditions!]

  "A set of conditions that requires at least one condition to match."
  OR: [PeopleHasRoleWhereConditions!]
}

"Allowed column names for the `hasRole` argument on the query `people`."
enum PeopleHasRoleColumn {
  NAME @enum(value: "name")
  ACCESS_LEVEL @enum(value: "access_level")
}
```

A simple query for a person who has an access level of at least 5, through one of
their roles, looks like this:

```graphql
{
  people(hasRole: { column: ACCESS_LEVEL, operator: GTE, value: 5 }) {
    name
  }
}
```

You can also query for relationship existence without any condition; simply use an empty object as argument value.
This query would retrieve all persons that have a role:

```graphql
{
  people(hasRole: {}) {
    name
  }
}
```

Just like with the `@whereCondition` directive, using `null` as argument value does not have any effect on the query.
This query would retrieve all persons, no matter if they have a role or not:

```graphql
{
  people(hasRole: null) {
    name
  }
}
```

## Custom operator

You may register a custom `\Nuwave\Lighthouse\WhereConditions\Operator` through a service provider.
This may be necessary if your database uses different SQL operators then Lighthouse's default or you
want to extend/restrict the allowed operators.
