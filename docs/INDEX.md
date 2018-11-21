---
home: true
heroImage: /logo.svg
actionText: Get Started  →
actionLink: /docs/latest

features:
- title: 🔤 Schema Directives
  details: Lighthouse provides you with a handful of helpful Schema Directives to get you up and running in no time. But it also allows you to create your own when needed.

- title: ❤️ Laravel Friendly
  details: Lighthouse integrates with your Laravel application without the need to re-write your entire domain. Just build a GraphQL schema on top of your current logic and start querying!

- title: 💯 Optimized for Eloquent
  details: Eloquent is an extremely powerful ORM. Lighthouse leverages your current model relationships and creates optimized database queries.

footer: Copyright © 2018 Christopher Moore
---

<br/>

```graphql
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

type Query {
  me: User @auth
  posts: [Post!]! @paginate
}
```

:::warning Rapid GraphQL Development

Lighthouse enables schema-first development by allowing you to use the native Schema Definition Language to describe your data. Leverage server-side directives to add functionality and bring your schema to life.

With nothing more than this schema file (along w/ Eloquent models and migrations set up), you have a fully functional GraphQL server with no additional code! But don't worry, you can extend Lighthouse to fit just about any data requirements. The docs will walk you through what directives are available, how to create your own directives and how to create your own resolvers, so let's get started!
:::

<br />

:::warning Laravel & GraphQL

Lighthouse dramatically reduces the amount of boilerplate needed to get a GraphQL project up and running. Many of the familiar concepts from Laravel are converted into Lighthouse directives, so you can reuse existing logic and work the way you are used to.

If you already have your models and migrations set up, it only takes minutes to get a GraphQL server up and running with Lighthouse!
:::

```graphql
  type Mutation {
    createPost(
      title: String! @rules(apply: ["min:2"])
      content: String! @rules(apply: ["min:12"])
    ): Post
    # Automatically create a new post model
    @create(model: "Post")
    # Inject the current user's id
    @inject(context: "user.id", attr: "user.id")
    # Fire an event with the newly created model
    @event(fire: "App\\Events\\PostCreated")
  }
```

<br/>
<br/>
