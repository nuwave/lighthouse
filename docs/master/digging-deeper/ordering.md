# Ordering

## Client Controlled Ordering

To enable clients to control the ordering, use [@orderBy](../api-reference/directives.md#orderby) on an argument of
a field that is backed by a database query.

```graphql
type Query {
  posts(orderBy: _ @orderBy(columns: ["posted_at", "title"])): [Post!]! @all
}
```

The type of the argument can be left blank as `_` ,
as Lighthouse will automatically generate an input that takes enumerated column names,
together with the `SortOrder` enum, and add that to your schema:

```graphql
"Order by clause for Query.posts.orderBy."
input QueryPostsOrderByOrderByClause {
  "The column that is used for ordering."
  column: QueryPostsOrderByColumn!

  "The direction that is used for ordering."
  order: SortOrder!
}

"Allowed column names for Query.posts.orderBy"
enum QueryPostsOrderByColumn {
  POSTED_AT @enum(value: "posted_at")
  TITLE @enum(value: "title")
}

"Directions for ordering a list of records."
enum SortOrder {
  "Sort records in ascending order."
  ASC

  "Sort records in descending order."
  DESC
}
```

Querying a field that has an `orderBy` argument looks like this:

```graphql
{
  posts(orderBy: [{ column: POSTED_AT, order: ASC }]) {
    title
  }
}
```

### Secondary Ordering

You may pass more than one sorting option to add a secondary ordering.

```graphql
{
  posts(
    orderBy: [{ column: POSTED_AT, order: ASC }, { column: TITLE, order: DESC }]
  ) {
    title
  }
}
```

### Reuse Columns Enum

To re-use a list of allowed columns, define your own enumeration type and use the `columnsEnum` argument instead of `columns`:

```graphql
type Query {
  allPosts(orderBy: _ @orderBy(columnsEnum: "PostColumn")): [Post!]! @all
  paginatedPosts(orderBy: _ @orderBy(columnsEnum: "PostColumn")): [Post!]!
    @paginate
}

"A custom description for this custom enum."
enum PostColumn {
  # Another reason why you might want to have a custom enum is to
  # correct typos or bad naming in column names.
  POSTED_AT @enum(value: "postd_timestamp")
  TITLE @enum(value: "title")
}
```

Lighthouse will still automatically generate the necessary input types and the `SortOrder` enum.
Instead of generating enums for the allowed columns, it will simply use the existing `PostColumn` enum.

### Ordering By Relations

You can allow clients to order a list of models by an aggregated value of their relations.
You must specify which relations and which of their columns are allowed.

```graphql
type Query {
  users(
    orderBy: _
      @orderBy(relations: [{ relation: "tasks", columns: ["difficulty"] }])
  ): [User!]! @all
}
```

Lighthouse will automatically generate the appropriate input types and enum values.

```graphql
{
  users(
    orderBy: [
      { tasks: { aggregate: COUNT }, order: ASC }
      { tasks: { aggregate: MAX, column: DIFFICULTY }, order: DESC }
    ]
  ) {
    id
  }
}
```

## Predefined Ordering

To predefine a default order for your field, use [@orderBy](../api-reference/directives.md#orderby) on a field:

```graphql
type Query {
  latestUsers: [User!]! @all @orderBy(column: "created_at", direction: DESC)
}
```

Clients won't have to pass any arguments to the field and still receive ordered results by default.
