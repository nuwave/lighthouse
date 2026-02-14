# Schema Organisation

Lighthouse reads your schema from a single entrypoint, by default `schema.graphql`.

As you grow your schema, it can be useful to split it across multiple files.
Use imports to include other files into your schema.

## Schema Imports

```
graphql/
|-- schema.graphql
|-- user.graphql
```

Imports start with `#import`, followed by the relative path to the imported file.

```graphql
type Query {
  user: User
}

#import user.graphql
```

The contents of `user.graphql` are pasted into the final schema.

```graphql
type Query {
  user: User
}

type User {
  name: String!
}
```

## Wildcard Imports

```
graphql/
  |-- schema.graphql
  |-- blog/
    |-- post.graphql
    |-- category.graphql
```

To import all schema files in `blog/` in one go, use wildcard import syntax (works like PHP's [glob function](https://php.net/manual/function.glob.php)).

```graphql
#import blog/*.graphql
```

## Type Extensions

Suppose you have split the definition for `Post` into `post.graphql`:

```graphql
type Post {
  title: String
  author: User @belongsTo
}
```

The definition is imported from `schema.graphql`:

```graphql
#import post.graphql
```

Now you want to add queries to allow fetching posts.
While you could add it to the main `Query` type in `schema.graphql`, it is generally preferable to colocate queries with the type they return.

Make sure `schema.graphql` contains a `Query` type.
You can add an empty type if you don't have one there:

```graphql
type Query
```

Then extend the `Query` type in `post.graphql`:

```graphql
type Post {
  title: String
  author: User @belongsTo
}

extend type Query {
  posts: [Post!]! @paginate
}
```

The fields in the `extend type` definition are merged with those of the original type.
Apart from object types `type`, you can also extend `input`, `interface` and `enum` types.
Lighthouse will merge the fields (or values) with the original definition and always produce a single type in the final schema.
