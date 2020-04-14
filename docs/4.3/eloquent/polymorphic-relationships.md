# Polymorphic Relationships

Just like in Laravel, you can define [Polymorphic Relationships](https://laravel.com/docs/eloquent-relationships#polymorphic-relationships) in your schema.

## One to One

Suppose you have defined a model structure just like the Laravel example docs.
You have two models, `Post` and `User` which may both have an `Image` assigned.

Let's start off with the plain type definitions, without any relations.

```graphql
type Post {
  id: ID!
  name: String!
}

type User {
  id: ID!
  name: String!
}

type Image {
  id: ID!
  url: String!
}
```

First, let's go ahead and add the relations to `Image` since they are straightforward.
The field name should match your relationship method name and be annotated
with the [`@morphOne`](../api-reference/directives.md#morphone) directive.

```graphql
type Post {
  id: ID!
  name: String!
  image: Image! @morphOne
}

type User {
  id: ID!
  name: String!
  image: Image @morphOne
}
```

Depending on the rules of your application, you might require the relationship
to be there in some cases, while allowing it to be absent in others. In this
example, a `Post` must always have an `Image`, while a `User` does not require one.

For the inverse, you will need to define a [union type](../the-basics/types.md#union)
to express that an `Image` might be linked to different models.

```graphql
union Imageable = Post | User
```

Now, reference the union type from a field in your `Image` type.
You can use the [`@morphTo`](../api-reference/directives.md#morphto) directive
for performance optimization.

```graphql
type Image {
  id: ID!
  url: String!
  imageable: Imageable! @morphTo
}
```

The default type resolver will be able to determine which concrete object type is returned
when dealing with Eloquent models, so your definition should just work.

## One to Many

Based on the above example, you could change your application to allow
for a `Post` to have many images attached to it.
The field `images` now returns a list of `Image` object and is annotated
with the [`@morphMany`](../api-reference/directives.md#morphmany) directive.

```graphql
type Post {
  id: ID!
  name: String!
  images: [Image]! @morphMany
}

type Image {
  id: ID!
  url: String!
  imageable: Imageable! @morphTo
}

union Imageable = Post | User
```
