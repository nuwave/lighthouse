# Nested Mutations

Lighthouse allows you to create, update or delete models and their associated relationships
all in one single mutation.

## Return Types Required

You have to define return types on your relationship methods so that Lighthouse
can detect them.

```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    // WORKS
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // DOES NOT WORK
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
}
```

## Partial Failure

By default, all mutations are wrapped in a database transaction.
If any of the nested operations fail, the whole mutation is aborted
and no changes are written to the database.

You can change this setting [in the configuration](../getting-started/configuration.md).

## Belongs To

We will start of by defining a mutation to create a post.

```graphql
type Mutation {
  createPost(input: CreatePostInput! @spread): Post @create
}
```

The mutation takes a single argument `input` that contains data about
the Post you want to create.

```graphql
input CreatePostInput {
  title: String!
  author: CreateAuthorRelation
}
```

The first argument `title` is a value of the `Post` itself and corresponds
to a column in the database.

The second argument `author`, exposes operations on the related `User` model.
It has to be named just like the relationship method that is defined on the `Post` model.

```graphql
input CreateAuthorRelation {
  connect: ID
  create: CreateUserInput
  update: UpdateUserInput
  upsert: UpsertUserInput
}
```

You can expose the following operations on a `BelongsTo` relationship when creating:

- `connect` it to an existing model
- `create` a new related model and attach it
- `update` an existing model and attach it
- `upsert` a new or an existing model and attach it

Finally, you need to define the input that allows you to create a new `User`.

```graphql
input CreateUserInput {
  name: String!
}
```

To create a new model and connect it to an existing model,
just pass the ID of the model you want to associate.

```graphql
mutation {
  createPost(input: { title: "My new Post", author: { connect: 123 } }) {
    id
    author {
      name
    }
  }
}
```

Lighthouse will create a new `Post` and associate an `User` with it.

```json
{
  "data": {
    "createPost": {
      "id": 456,
      "author": {
        "name": "Herbert"
      }
    }
  }
}
```

If the related model does not exist yet, you can also
create a new one.

```graphql
mutation {
  createPost(
    input: { title: "My new Post", author: { create: { name: "Gina" } } }
  ) {
    id
    author {
      id
    }
  }
}
```

```json
{
  "data": {
    "createPost": {
      "id": 456,
      "author": {
        "id": 55
      }
    }
  }
}
```

When issuing an update, you can also allow the user to remove a relation.
Both `disconnect` and `delete` remove the association to the author,
but `delete` also removes the author model itself.

```graphql
type Mutation {
  updatePost(input: UpdatePostInput! @spread): Post @update
}

input UpdatePostInput {
  id: ID!
  title: String
  author: UpdateAuthorRelation
}

input UpdateAuthorRelation {
  connect: ID
  create: CreateUserInput
  update: UpdateUserInput
  disconnect: Boolean
  delete: Boolean
}
```

You must pass a truthy value to `disconnect` and `delete` for them to actually run.
This structure was chosen as it is consistent with updating `BelongsToMany` relationships
and allows the query string to be mostly static, taking a variable value to control its behaviour.

```graphql
mutation UpdatePost($disconnectAuthor: Boolean) {
  updatePost(
    input: {
      id: 1
      title: "An updated title"
      author: { disconnect: $disconnectAuthor }
    }
  ) {
    title
    author {
      name
    }
  }
}
```

The `author` relationship will only be disconnected if the value of the variable
`$disconnectAuthor` is `true`, if `false` or `null` are passed, it will not change.

```json
{
  "data": {
    "updatePost": {
      "id": 1,
      "title": "An updated title",
      "author": null
    }
  }
}
```

When issuing an `upsert`, you may expose the same nested operations as an `update`.
In case a new model is created, they will simply be ignored.

```graphql
mutation UpdatePost($disconnectAuthor: Boolean) {
  upsertPost(
    input: {
      id: 1
      title: "An updated or created title"
      author: { disconnect: $disconnectAuthor }
    }
  ) {
    id
    title
    author {
      name
    }
  }
}
```

```json
{
  "data": {
    "upsertPost": {
      "id": 1,
      "title": "An updated or created title",
      "author": null
    }
  }
}
```

## Has Many

The counterpart to a `BelongsTo` relationship is `HasMany`. We will start
of by defining a mutation to create an `User`.

```graphql
type Mutation {
  createUser(input: CreateUserInput! @spread): User @create
}
```

This mutation takes a single argument `input` that contains values
of the `User` itself and its associated `Post` models.

```graphql
input CreateUserInput {
  name: String!
  posts: CreatePostsRelation
}
```

Now, we can an operation that allows us to directly create new posts
right when we create the `User`.

```graphql
input CreatePostsRelation {
  create: [CreatePostInput!]!
}

input CreatePostInput {
  title: String!
}
```

You can now create a `User` and some posts with it in one request.

```graphql
mutation {
  createUser(
    input: {
      name: "Phil"
      posts: {
        create: [
          { title: "Phils first post" }
          { title: "Awesome second post" }
        ]
      }
    }
  ) {
    id
    posts {
      id
    }
  }
}
```

```json
{
  "data": {
    "createUser": {
      "id": 23,
      "posts": [
        {
          "id": 434
        },
        {
          "id": 435
        }
      ]
    }
  }
}
```

When updating a `User`, further nested operations become possible.
It is up to you which ones you want to expose through the schema definition.

The following example covers the full range of possible operations:

```graphql
type Mutation {
  updateUser(input: UpdateUserInput! @spread): User @update
}

input UpdateUserInput {
  id: ID!
  name: String
  posts: UpdatePostsRelation
}

input UpdatePostsRelation {
  create: [CreatePostInput!]
  update: [UpdatePostInput!]
  upsert: [UpsertPostInput!]
  delete: [ID!]
}

input CreatePostInput {
  title: String!
}

input UpdatePostInput {
  id: ID!
  title: String
}

input UpsertPostInput {
  id: ID!
  title: String
}
```

```graphql
mutation {
  updateUser(
    input: {
      id: 3
      name: "Phillip"
      posts: {
        create: [{ title: "A new post" }]
        update: [{ id: 45, title: "This post is updated" }]
        delete: [8]
      }
    }
  ) {
    id
    posts {
      id
    }
  }
}
```

The behaviour for `upsert` is a mix between updating and creating,
it will produce the needed action regardless of whether the model exists or not.

## Belongs To Many

A belongs to many relation allows you to create new related models as well
as attaching existing ones.

```graphql
type Mutation {
  createPost(input: CreatePostInput! @spread): Post @create
}

input CreatePostInput {
  title: String!
  authors: CreateAuthorRelation
}

input CreateAuthorRelation {
  create: [CreateAuthorInput!]
  upsert: [UpsertAuthorInput!]
  connect: [ID!]
  sync: [ID!]
}

input CreateAuthorInput {
  name: String!
}

input UpsertAuthorInput {
  id: ID!
  name: String!
}
```

Just pass the ID of the models you want to associate or their full information
to create a new relation.

```graphql
mutation {
  createPost(
    input: {
      title: "My new Post"
      authors: {
        create: [{ name: "Herbert" }]
        upsert: [{ id: 2000, name: "Newton" }]
        connect: [123]
      }
    }
  ) {
    id
    authors {
      name
    }
  }
}
```

Lighthouse will detect the relationship and attach/update/create it.

```json
{
  "data": {
    "createPost": {
      "id": 456,
      "authors": [
        {
          "id": 165,
          "name": "Herbert"
        },
        {
          "id": 2000,
          "name": "Newton"
        },
        {
          "id": 123,
          "name": "Franz"
        }
      ]
    }
  }
}
```

It is also possible to use the `sync` operation to ensure only the given IDs
will be contained withing the relation.

```graphql
mutation {
  createPost(input: { title: "My new Post", authors: { sync: [123] } }) {
    id
    authors {
      name
    }
  }
}
```

Updates on `BelongsToMany` relations may expose the following nested operations:

```graphql
type Mutation {
  updatePost(input: UpdatePostInput! @spread): Post @update
}

input UpdatePostInput {
  id: ID!
  title: String
  authors: UpdateAuthorRelation
}

input UpdateAuthorRelation {
  create: [CreateAuthorInput!]
  connect: [ID!]
  update: [UpdateAuthorInput!]
  upsert: [UpsertAuthorInput!]
  sync: [ID!]
  delete: [ID!]
  disconnect: [ID!]
}

input CreateAuthorInput {
  name: String!
}

input UpdateAuthorInput {
  id: ID!
  name: String!
}

input UpsertAuthorInput {
  id: ID!
  name: String!
}
```

## MorphTo

**The GraphQL Specification does not support Input Union types,
for now we are limiting this implementation to `connect`, `disconnect` and `delete` operations.
See https://github.com/nuwave/lighthouse/issues/900 for further discussion.**

```graphql
type Task {
  id: ID
  name: String
}

type Hour {
  id: ID
  weekday: Int
  hourable: Task
}

type Mutation {
  createHour(input: CreateHourInput! @spread): Hour @create
  updateHour(input: UpdateHourInput! @spread): Hour @update
  upsertHour(input: UpsertHourInput! @spread): Hour @upsert
}

input CreateHourInput {
  from: String
  to: String
  weekday: Int
  hourable: CreateHourableOperations
}

input UpdateHourInput {
  id: ID!
  from: String
  to: String
  weekday: Int
  hourable: UpdateHourableOperations
}

input UpsertHourInput {
  id: ID!
  from: String
  to: String
  weekday: Int
  hourable: UpsertHourableOperations
}

input CreateHourableOperations {
  connect: ConnectHourableInput
}

input UpdateHourableOperations {
  connect: ConnectHourableInput
  disconnect: Boolean
  delete: Boolean
}

input UpsertHourableOperations {
  connect: ConnectHourableInput
  disconnect: Boolean
  delete: Boolean
}

input ConnectHourableInput {
  type: String!
  id: ID!
}
```

You can use `connect` to associate existing models.

```graphql
mutation {
  createHour(
    input: {
      weekday: 2
      hourable: { connect: { type: "App\\Models\\Task", id: 1 } }
    }
  ) {
    id
    weekday
    hourable {
      id
      name
    }
  }
}
```

The `disconnect` operations allows you to detach the currently associated model.

```graphql
mutation {
  updateHour(input: { id: 1, weekday: 2, hourable: { disconnect: true } }) {
    weekday
    hourable {
      id
      name
    }
  }
}
```

The `delete` operation both detaches and deletes the currently associated model.

```graphql
mutation {
  upsertHour(input: { id: 1, weekday: 2, hourable: { delete: true } }) {
    weekday
    hourable {
      id
      name
    }
  }
}
```

## Morph To Many

A morph to many relation allows you to create new related models as well
as attaching existing ones.

```graphql
type Mutation {
  createTask(input: CreateTaskInput! @spread): Task @create
}

input CreateTaskInput {
  name: String!
  tags: CreateTagRelation
}

input CreateTagRelation {
  create: [CreateTagInput!]
  upsert: [UpsertTagInput!]
  sync: [ID!]
  connect: [ID!]
}

input CreateTagInput {
  name: String!
}

input UpsertTagInput {
  id: ID!
  name: String!
}

type Task {
  id: ID!
  name: String!
  tags: [Tag!]!
}

type Tag {
  id: ID!
  name: String!
}
```

In this example, the tag with id `1` already exists in the database. The query connects this tag to the task using the `MorphToMany` relationship.

```graphql
mutation {
  createTask(input: { name: "Loundry", tags: { connect: [1] } }) {
    tags {
      id
      name
    }
  }
}
```

You can either use `connect` or `sync` during creation.

When you want to create a new tag while creating the task,
you need to use the `create` operation to provide an array of `CreateTagInput`
or use the `upsert` operation to provide an array of `UpsertTagInput`:

```graphql
mutation {
  createTask(input: { name: "Loundry", tags: { create: [{ name: "home" }] } }) {
    tags {
      id
      name
    }
  }
}
```
