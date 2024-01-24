# Soft Deleting

Lighthouse offers convenient helpers to work with models that utilize
[soft deletes](https://laravel.com/docs/eloquent#soft-deleting).

## Filter Soft Deleted Models

If your model uses the `Illuminate\Database\Eloquent\SoftDeletes` trait,
you can add the [@softDeletes](../api-reference/directives.md#softdeletes) directive to a field
to be able to query `onlyTrashed`, `withTrashed` or `withoutTrashed` elements.

```graphql
type Query {
  flights: [Flight!]! @all @softDeletes
}
```

Lighthouse will automatically add an argument `trashed` to the field definition
and include the enum `Trashed`.

```graphql
type Query {
  flights(trashed: Trashed @trashed): [Flight!]! @all
}

"""
Used for filtering
"""
enum Trashed {
  ONLY @enum(value: "only")
  WITH @enum(value: "with")
  WITHOUT @enum(value: "without")
}
```

You can include soft deleted models in your result with a query like this:

```graphql
{
  flights(trashed: WITH) {
    id
  }
}
```

## Restoring Soft Deleted Models

If your model uses the `Illuminate\Database\Eloquent\SoftDeletes` trait,
you can restore your model using the [@restore](../api-reference/directives.md#restore) directive.

```graphql
type Mutation {
  restoreFlight(id: ID!): Flight @restore
}
```

Simply call the field with the ID of the flight you want to restore.

```graphql
mutation {
  restoreFlight(id: 1) {
    id
  }
}
```

This mutation will return the restored object.

## Permanently Deleting Models

To truly remove a model from the database,
use the [@forceDelete](../api-reference/directives.md#forcedelete) directive.
Your model must use the `Illuminate\Database\Eloquent\SoftDeletes` trait.

```graphql
type Mutation {
  forceDeleteFlight(id: ID!): Flight @forceDelete
}
```

Simply call it with the ID of the `Flight` you want to permanently remove.

```graphql
mutation {
  forceDeleteFlight(id: 5) {
    id
  }
}
```

This mutation will return the deleted object, so you will have a last chance to look at the data.

```json
{
  "data": {
    "forceDeleteFlight": {
      "id": 5
    }
  }
}
```
