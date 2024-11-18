# Types

A GraphQL schema is made out of types. This section describes the different set of types
and how they can be defined to work with Lighthouse. For a more in-depth reference about types,
look into the [GraphQL documentation](https://graphql.org/learn/schema)

## Object Type

Object types define the resources of your API and are closely related to Eloquent models.
They must have a unique name and contain a set of fields.

```graphql
type User {
  id: ID!
  name: String!
  email: String!
  created_at: String!
  updated_at: String
}

type Query {
  users: [User!]!
  user(id: ID!): User
}
```

## Scalar

Scalar types are the most basic elements of a GraphQL schema. There are a
few built-in scalars, such as `String` or `Int`.

Lighthouse provides some scalars that work well with Laravel out of the box,
read about them in the [API reference for scalars](../api-reference/scalars.md).

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

[Learn how to implement your own scalar.](https://webonyx.github.io/graphql-php/type-definitions/scalars)

## Enum

Enums are types with a restricted set of values (similar to `enum` found in database migrations).
By convention, they are defined as a list of `UPPER_CASE` string keys.

### Schema definition

You can define the actual values through the [@enum](../api-reference/directives.md#enum) directive.

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
      { "name": "Hans", "status": "INTERN" },
      { "name": "Pamela", "status": "EMPLOYEE" },
      { "name": "Gerhard", "status": "TERMINATED" }
    ]
  }
}
```

If the internal value of the enum is the same as the field name, [@enum](../api-reference/directives.md#enum) can be omitted:

```graphql
enum Role {
  ADMIN
}
```

The PHP internal value of the field `ADMIN` will be `string('ADMIN')`.

### Native PHP definition

If you want to reuse enum definitions from PHP, you can [construct a `PhpEnumType`](https://webonyx.github.io/graphql-php/type-definitions/enums/#construction-from-php-enum)
and [register it through the TypeRegistry](../digging-deeper/adding-types-programmatically.md#native-php-types):

```php
use GraphQL\Type\Definition\Deprecated;
use GraphQL\Type\Definition\Description;
use GraphQL\Type\Definition\PhpEnumType;
use Nuwave\Lighthouse\Schema\TypeRegistry;

#[Description(description: 'Sweet and juicy.')]
enum Fruit
{
    #[Description(description: 'Rich in potassium.')]
    case BANANA;

    #[Deprecated(reason: 'Too sour.')]
    case CITRON;
}

// This code should go in a service provider
// Resolve TypeRegistry through the container, as it is a singleton
$typeRegistry = app(TypeRegistry::class);
$typeRegistry->register(new PhpEnumType(Fruit::class));
```

### bensampo/laravel-enum

If you are using [bensampo/laravel-enum](https://github.com/BenSampo/laravel-enum)
you can use `Nuwave\Lighthouse\Schema\Types\LaravelEnumType` to construct an enum type from it.

```php
use BenSampo\Enum\Enum;
use Nuwave\Lighthouse\Schema\Types\LaravelEnumType;

final class Color extends Enum
{
    public const BLACK = 0;

    public const WHITE = 1;

    /** @deprecated too colorful */
    public const YELLOW = 2;
}

// Register through TypeRegistry::register()
$colorEnumType = new LaravelEnumType(Color::class);
```

The generated enum will be named after the class and have values equivalent to the keys:

```graphql
enum Color {
  """
  Black
  """
  BLACK
  """
  White
  """
  WHITE
  """
  Yellow
  """
  YELLOW @deprecated(reason: "too colorful")
}
```

You may overwrite the name if the default does not fit, or you have a name conflict.

```php
// API uses british english
new LaravelEnumType(Color::class, 'Colour');
```

You may customize Enum and value descriptions:

```php
use BenSampo\Enum\Enum;
use BenSampo\Enum\Attributes\Description;
use Nuwave\Lighthouse\Schema\Types\LaravelEnumType;

#[Description('Available theme colors')]
final class Color extends Enum
{
    #[Description('Black theme')]
    public const BLACK = 0;

    #[Description('White theme')]
    public const WHITE = 1;

    /** @deprecated too colorful */
    public const YELLOW = 2;
}

// Register through TypeRegistry::register()
$userType = new LaravelEnumType(Color::class);
```

The generated enum will be as such:

```graphql
"""
Available theme Colors
"""
enum Color {
  """
  Black theme
  """
  BLACK
  """
  White theme
  """
  WHITE
  """
  Yellow
  """
  YELLOW @deprecated(reason: "too colorful")
}
```

## Input

Input types can be used to describe complex objects for field arguments.
Beware that while they look similar to Object Types, they behave differently:
The fields of an Input Type are treated similar to arguments.

```graphql
input CreateUserInput {
  name: String!
  email: String
}

type User {
  id: ID!
  name: String!
  email: String
}

type Mutation {
  createUser(input: CreateUserInput! @spread): User! @create
}
```

## Interface

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
[docs for graphql-php](https://webonyx.github.io/graphql-php/type-definitions/interfaces)

## Union

A Union is an abstract type that simply enumerates other Object Types.
They are similar to interfaces in that they can return different types, but do not prescribe any common fields.

```graphql
union Person = User | Employee

type User {
  id: ID!
  email: String!
}

type Employee {
  id: ID!
  department: Department!
}
```

Just like Interfaces, you need a way to determine the concrete Object Type for a Union,
based on the resolved value. If the default type resolver does not work for you, define your
own using `php artisan lighthouse:union <Union name>`.
It is automatically put in the default namespace where Lighthouse can discover it by itself.

Read more about them in the [GraphQL Reference](https://graphql.org/learn/schema/#union-types) and the
[docs for graphql-php](https://webonyx.github.io/graphql-php/type-definitions/unions)
