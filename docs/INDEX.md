---
home: true
title: Lighthouse - A framework for serving GraphQL from Laravel
heroImage: /logo.svg
actionText: Get Started  →
actionLink: /docs/latest
features:
  - title: 📜 SDL First
    details: Use the GraphQL Schema Definition Language to describe your data and add functionality through server-side directives.
  - title: ❤ Laravel Friendly
    details: Build a GraphQL server on top of an existing Laravel application. Maximize code reuse and work with concepts you already know.
  - title: ⚡ Optimized for Eloquent
    details: Lighthouse leverages your existing models and creates optimized database queries out of the box.
footer: Made with ❤ by people
---

### Boilerplate free schema definition

Define your schema without any boilerplate by using the GraphQL Schema Definition Language.

```graphql
type User {
  name: String!
  posts: [Post!]! @hasMany
}

type Post {
  title: String!
  author: User @belongsTo
}

type Query {
  me: User @auth
  posts: [Post!]! @paginate
}

type Mutation {
  createPost(
    title: String @rules(apply: ["required", "min:2"])
    content: String @rules(apply: ["required", "min:12"])
  ): Post @create
}
```

### Query just what you need

In a GraphQL query, the client can get all the data they need - and no more -
all in a single request.

```graphql
query PostsWithAuthor {
  posts {
    title
    author {
      name
    }
  }
}
```

### Get predictable results

A GraphQL server can tell clients about its schema, so they will always
know exactly what they will get.

```json
{
  "data": {
    "posts": [
      {
        "title": "Lighthouse rocks",
        "author": {
          "name": "Albert Einstein"
        }
      },
      {
        "title": "World peace achieved through GraphQL",
        "author": {
          "name": "New York Times"
        }
      }
    ]
  }
}
```
