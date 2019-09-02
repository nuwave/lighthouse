# Soft Deleting

Lighthouse offers convenient helpers to work with models that utilize
[soft deletes](https://laravel.com/docs/eloquent#soft-deleting).

## Filter Soft Deleted Models

If your model uses the `Illuminate\Database\Eloquent\SoftDeletes` trait,
you can add the [`@softDeletes`](../api-reference/directives.md) directive to a field
to be able to query `onlyTrashed`, `withTrashed` or `withoutTrashed` elements.

```graphql
type Query {
  flights: [Flight!]! @all @softDeletes
}
```

Lighthouse will add an argument `trashed` to the field definition
and automatically include the enum `Trashed`.

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
