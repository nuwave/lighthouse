---
home: true
heroImage: /logo.svg
actionText: Get Started  →
actionLink: /docs/latest

features:
- title: 📜 Schema First
  details: Lighthouse allows you to use the native Schema Definition Language to describe your data. Leverage server-side directives to add functionality and bring your schema to life.

- title: ❤️ Laravel Friendly
  details: Lighthouse integrates with your Laravel application without the need to re-write your entire domain. Just build a GraphQL schema on top of your current logic and start querying!

- title: ⚡ Optimized for Eloquent
  details: Eloquent is an extremely powerful ORM. Lighthouse leverages your current model relationships and creates optimized database queries.

footer: Copyright © 2018 Christopher Moore
---

<br/>

```graphql
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

type User {
  id: ID!
  name: String!
  email: String
  posts: [Post!]! @hasMany
}

type Post {
  title: String!
  content: String!
  author: User @belongsTo
}
```

:::warning Rapid GraphQL Development with Laravel

Lighthouse dramatically reduces the amount of boilerplate needed to get a GraphQL project up and running. Many of the familiar concepts from Laravel are converted into Lighthouse directives, so you can reuse existing logic and work the way you are used to.

If you already have your models and migrations set up, it only takes minutes to get up and running with Lighthouse.
You only need to define a schema to have a fully functional GraphQL server with no additional code.

But don't worry, you can extend Lighthouse to fit just about any data requirements. The docs will walk you through what directives are available, how to create your own directives and how to create your own resolvers, so let's get started!
:::

<br/>
<br/>
