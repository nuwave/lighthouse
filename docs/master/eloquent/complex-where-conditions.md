# Complex Where Conditions

**Experimental: not enabled by default, not guaranteed to be stable.**

Adding query conditions ad-hoc can be cumbersome and limiting when you require
manifold ways to filter query results.
Lighthouse's `WhereConditions` extension can give advanced query capabilities to clients
and allow them to apply complex and dynamic filters.

## Setup

Add the service provider to your `config/app.php`:

```php
'providers' => [
    \Nuwave\Lighthouse\WhereConditions\WhereConditionsServiceProvider::class,
],
```

Install the dependency [mll-lab/graphql-php-scalars](https://github.com/mll-lab/graphql-php-scalars):

    composer require mll-lab/graphql-php-scalars

## @whereConditions

```graphql
"""
Add a dynamically client-controlled WHERE condition to a fields query.
"""
directive @whereConditions(
  """
  Restrict the allowed column names to a well-defined enum.
  This improves introspection capabilities and security.
  Mutually exclusive with the `columnsEnum` argument.
  """
  columns: [String!]

  """
  Use an existing enum type to restrict the allowed columns to a well-defined enum.
  This allows you to re-use the same enum for multiple fields.
  Mutually exclusive with the `columns` argument.
  """
  columnsEnum: String

  """
  Restrict the allowed relation names to a well-defined enum.
  This improves introspection capabilities and security.
  Mutually exclusive with the `relationsEnum` argument.
  """
  relations: [String!]

  """
  Use an existing enum type to restrict the allowed relations to a well-defined enum.
  This allows you to re-use the same enum for multiple fields.
  Mutually exclusive with the `relations` argument.
  """
  relationsEnum: String

  """
  Reference a method that applies the client given conditions to the query builder.

  Expected signature: `(
      \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $builder,
      array<string, mixed> $whereConditions
  ): void`

  Consists of two parts: a class name and a method name, separated by an `@` symbol.
  If you pass only a class name, the method name defaults to `__invoke`.
  """
  handler: String = "\\Nuwave\\Lighthouse\\WhereConditions\\WhereConditionsHandler"
) on ARGUMENT_DEFINITION
```

### Basic usage

You can apply this directive on any field that performs an Eloquent query.

It is recommended to use both:

- the `columns` or the `columnsEnum` argument
- the `relations` or the `relationsEnum` argument.

When you don't define allowed values, clients can specify arbitrary `String` values,
which poses a risk to performance, security and type safety.

```graphql
type Query {
  people(
    where: _
      @whereConditions(
        columns: ["age", "type", "hair_color", "height"]
        relations: ["friends", "friends.friends"]
      )
  ): [Person!]! @all
}

type Person {
  id: ID!
  age: Int!
  height: Int!
  type: String!
  hair_color: String!
  friends: [Person!]! @hasMany
}
```

### Generated code

Lighthouse automatically generates definitions for an `Enum` type and an `Input` type
that are restricted to the defined columns, so you do not have to specify them by hand.
The blank type named `_` will be changed to the actual type.
Here are the types that will be included in the compiled schema:

```graphql
"Dynamic WHERE conditions for Query.people.where."
input QueryPeopleWhereWhereConditions {
  "The column that is used for the condition."
  column: QueryPeopleWhereColumn

  "The operator that is used for the condition."
  operator: SQLOperator = EQ

  "The value that is used for the condition."
  value: Mixed

  "A set of conditions that requires all conditions to match."
  AND: [QueryPeopleWhereWhereConditions!]

  "A set of conditions that requires at least one condition to match."
  OR: [QueryPeopleWhereWhereConditions!]

  "Check whether a relation exists. Extra conditions or a minimum amount can be applied."
  HAS: QueryPeopleWhereWhereConditionsRelation
}

"Allowed column names for Query.people.where."
enum QueryPeopleWhereColumn {
  AGE @enum(value: "age")
  TYPE @enum(value: "type")
  HAIR_COLOR @enum(value: "hair_color")
  HEIGHT @enum(value: "height")
}

"Dynamic HAS conditions for Query.people.where."
input QueryPeopleWhereWhereConditionsRelation {
  "The relation that is checked."
  relation: QueryPeopleWhereRelation!

  "The comparison operator to test against the amount."
  operator: SQLOperator = GTE

  "The amount to test."
  amount: Int = 1

  "Additional condition logic."
  condition: QueryPeopleWhereWhereConditions
}

"Allowed relation names for Query.people.where."
enum QueryPeopleWhereRelation {
  FRIENDS @enum(value: "friends")
  FRIENDS__FRIENDS @enum(value: "friends.friends")
}
```

### Reuse existing enum

Alternatively to the `columns` argument, you can also use `columnsEnum` in case you
want to re-use an enum of allowed columns. Here's how your schema could look like:

```graphql
type Query {
  allPeople(where: _ @whereConditions(columnsEnum: "PersonColumn")): [Person!]!
    @all

  paginatedPeople(
    where: _ @whereConditions(columnsEnum: "PersonColumn")
  ): [Person!]! @paginated
}

"Filterable columns of Person."
enum PersonColumn {
  AGE @enum(value: "age")
  TYPE @enum(value: "type")
  HAIR_COLOR @enum(value: "hair_color")
  HEIGHT @enum(value: "height")
}
```

Lighthouse will still automatically generate the necessary input types.
Instead of creating enums for the allowed columns, it will simply use the existing `PersonColumn` enum.

The same works for `relationColumns`.

### Example queries

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

The following query gets actors over age 37 who either have red hair or are at least 150 cm:

```graphql
{
  people(
    where: {
      AND: [
        { column: AGE, operator: GT, value: 37 }
        { column: TYPE, value: "Actor" }
        {
          OR: [
            { column: HAIR_COLOR, value: "red" }
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
        { column: HAIR_COLOR, operator: IS_NULL }
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

### Has Relations

Use the `HAS` clause to filter by relation existence.
This query retrieves all persons that have at least 1 role.

```graphql
{
  people(where: { HAS: { relation: ROLE, amount: 1, operator: GTE } }) {
    name
  }
}
```

The default values for `amount` and `operator` are included in the previous example,
you can also omit them:

```graphql
{
  people(where: { HAS: { relation: ROLE } }) {
    name
  }
}
```

You can also add additional
This filters people who have an access level of at least 5 through one of their roles:

```graphql
{
  people(
    where: {
      HAS: {
        relation: ROLE
        amount: 1
        operator: GTE
        condition: { column: ACCESS_LEVEL, operator: GTE, value: 5 }
      }
    }
  ) {
    name
  }
}
```

## Custom operator

If Lighthouse's default `SQLOperator` does not fit your use case, you can register a custom operator class.
This may be necessary if your database uses different SQL operators then Lighthouse's default,
or you want to extend/restrict the allowed operators.

First create a class that implements `\Nuwave\Lighthouse\WhereConditions\Operator`. For example:

```php
namespace App\GraphQL;

use Nuwave\Lighthouse\WhereConditions\Operator;

class CustomSQLOperator implements Operator { ... }
```

An `Operator` has two responsibilities:

- provide an `enum` definition that will be used throughout the schema
- handle client input and apply the operators to the query builder

To tell Lighthouse to use your custom operator class, you have to bind it in a service provider:

```php
namespace App\GraphQL;

use App\GraphQL\CustomSQLOperator;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\WhereConditions\Operator;

class GraphQLServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Operator::class, CustomSQLOperator::class);
    }
}
```

Don't forget to register your new service provider in `config/app.php`.
Make sure to add it after Lighthouse's `\Nuwave\Lighthouse\WhereConditions\WhereConditionsServiceProvider::class`:

```diff
'providers' => [
    /*
     * Package Service Providers...
     */
    \Nuwave\Lighthouse\WhereConditions\WhereConditionsServiceProvider::class,

    /*
     * Application Service Providers...
     */
+   \App\GraphQL\GraphQLServiceProvider::class,
],
```

## Custom handler

If you want to take advantage of the schema generation that [@whereConditions](#whereconditions)
and [@whereHasConditions](#wherehasconditions) provide, but customize the application of arguments
to the query builder, you can provide a custom handler.

```graphql
type Query {
  people(
    where: _ @whereConditions(columns: ["age"], handler: "App\\MyCustomHandler")
  ): [Person!]! @all
}
```

When a client passes `where`, your handler will be called with the query builder and
the passed conditions:

```php
namespace App;

class MyCustomHandler {
    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  array<string, mixed>  $whereConditions
     */
    public function __invoke(object $builder, array $whereConditions): void
    {
        // TODO make calls to $builder depending on $whereConditions
    }
}
```
