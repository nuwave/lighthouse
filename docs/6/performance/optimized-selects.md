# Optimized Selects

By default, Lighthouse selects all columns of a model with `SELECT *`, no matter which fields a client queries.
For tables with many or large columns, use [@select](../api-reference/directives.md#select) to select only the columns the queried fields need.
Without package discovery, e.g. in Lumen, register `\Nuwave\Lighthouse\Select\SelectServiceProvider`.

```graphql
type Query {
  users: [User!]! @all
}

type User {
  id: ID! @select(columns: ["id"])
  name: String! @select(columns: ["name"])
  fullName: String! @select(columns: ["first_name", "last_name"])
  posts: [Post!]! @hasMany
}
```

```graphql
{
  users {
    fullName
    posts {
      title
    }
  }
}
```

```sql
select `users`.`id`, `users`.`first_name`, `users`.`last_name` from `users`
```

## Opting In

A type opts in when the type or one of its fields uses `@select`.
All other types keep `SELECT *`, so you can adopt the optimization one type at a time.

Lighthouse can not see which attributes a field reads: accessors, casts, `@method`, `@rename` and custom resolvers may read any of them.
Thus, every field of an opted-in type must declare its columns with `@select`, else building the schema fails.
Use `@select(columns: [])` for fields that need no columns.

Fields with a relation directive such as [@hasMany](../api-reference/directives.md#hasmany), [@belongsTo](../api-reference/directives.md#belongsto), [@morphTo](../api-reference/directives.md#morphto), [@count](../api-reference/directives.md#count) or [@aggregate](../api-reference/directives.md#aggregate) need no `@select`.
Lighthouse selects the keys that loading the relation needs, such as the foreign key of a `BelongsTo` relation.
To find them, Lighthouse calls the relation method on a new model instance, so the method must not depend on the model's attributes.
Directives that only load a relation, such as [@with](../api-reference/directives.md#with) or [@withCount](../api-reference/directives.md#withcount), also add those keys, but the field still needs `@select` for its own value.
The same holds for `@aggregate` with the `builder` argument, since the builder receives the parent model.
Lighthouse also selects the keys of relations the query already loads, e.g. through the model's `$with`.

Lighthouse always selects the primary key.
It qualifies all columns with the table name, so joins in scopes or builders can not overwrite them.

## Columns Every Query Needs

Some code reads attributes outside of field resolution: policies, model events or relations that scopes load.
List the columns they need in `@select` on the type, Lighthouse selects them regardless of the queried fields.

```graphql
type Post @select(columns: ["user_id"]) {
  id: ID! @select(columns: ["id"])
  title: String! @select(columns: ["title"])
}
```

A policy run through [@canResolved](../api-reference/directives.md#canresolved) or [@canRoot](../api-reference/directives.md#canroot) may read any attribute of the model it checks.
If such a model has an opted-in type, the type must use `@select`, else building the schema fails.
Lighthouse can not check this when a field passes its parent model on to another type, e.g. through a custom resolver.

## Supported Resolvers

Lighthouse optimizes the queries of [@all](../api-reference/directives.md#all), [@find](../api-reference/directives.md#find), [@first](../api-reference/directives.md#first) and [@paginate](../api-reference/directives.md#paginate).
Models loaded through relation directives are not optimized yet.

Lighthouse keeps `SELECT *` when:

- the query already selects columns, e.g. in the method referenced by the `builder` argument
- the `builder` argument returns a relation or a query builder instead of an Eloquent builder
- the field returns an interface or a union
- the field uses a custom resolver or Laravel Scout

Scopes run after Lighthouse selects the columns.
A scope that calls `select()` replaces them, `addSelect()` adds to them.

## Detecting Missing Columns

When a column was not selected, Eloquent returns `null` for it without an error.
Enable [`Model::preventAccessingMissingAttributes()`](https://laravel.com/docs/eloquent#configuring-eloquent-strictness) outside of production to make Eloquent throw instead.

A policy that reads a column you did not select sees `null`, which may grant access it should deny.
List every column your policies read in `@select` on the type.
