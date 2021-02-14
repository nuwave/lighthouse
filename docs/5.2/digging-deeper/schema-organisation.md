# Schema Organisation

As you add more and more types to your schema, it can grow quite large.
Learn how to split your schema across multiple files and organise your types.

## Schema Imports

Suppose you created your schema files likes this:

```
graphql/
|-- schema.graphql
|-- user.graphql
```

Lighthouse reads your schema from a single entrypoint, in this case `schema.graphql`.
You can import other schema files from there to split up your schema into multiple files.

```graphql
type Query {
  user: User
}

#import user.graphql
```

Imports always begin on a separate line with `#import`, followed by the relative path
to the imported file. The contents of `user.graphql` are pasted in the final schema.

```graphql
type Query {
  user: User
}

type User {
  name: String!
}
```

The import statements are followed recursively, so it is easy to organize even the most complex of schemas.

You can also import multiple files using wildcard import syntax.
For example, if you have your schema files like this:

```
graphql/
  |-- schema.graphql
  |-- post/
    |-- post.graphql
    |-- category.graphql
```

Instead of naming each individual file, you can import multiple files that matches a pattern.
It will be loaded using PHP's [glob function](https://php.net/manual/function.glob.php).

```graphql
#import post/*.graphql
```

## Type Extensions

Suppose you want to add a new type `Post` to your schema.
Create a new file `post.graphql` with the schema for that type.

```graphql
type Post {
  title: String
  author: User @belongsTo
}
```

Then you add an import to your main schema file.

```graphql
#import post.graphql

type Query {
  me: User @auth
}
```

Now you want to add a few queries to actually fetch posts. You could add them to the main `Query` type
in your main file, but that spreads the definition apart, and could also grow quite large over time.

Another way would be to extend the `Query` type and colocate the type definition with its Queries in `post.graphql`.

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

### Extending other types

Apart from object types `type`, you can also extend `input`, `interface` and `enum` types.
Lighthouse will merge the fields (or values) with the original definition and always
produce a single type in the final schema.
