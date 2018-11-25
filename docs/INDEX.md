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

<div class="highlights">
  <div class="highlight">

  ```graphql
    type User {
      id: ID!
      name: String!
      email: String
      posts: [Post!] @hasMany
    }

    type Post {
      title: String!
      content: String!
      author: User @belongsTo
    }

    type Query {
      me: User @auth
      posts: [Post!] @paginate
    }
  ```

  </div>
  <div class="highlight">
    <h2>Rapid GraphQL Development with Laravel</h2>
    <p>
      Lighthouse enables schema-first development by allowing you to use the native Schema Definition Language to describe your data. Leverage server-side directives to add functionality and bring your schema to life. 
    </p>
    <p>
      With nothing more than this schema file (along w/ Eloquent models and migrations set up), you have a fully functional GraphQL server with no additional code! But don't worry, you can extend Lighthouse to fit just about any data requirements. 
    </p>
    <p>
      The docs will walk you through what directives are available, how to create your own directives and how to create your own resolvers.
    </p>
  </div>
</div>
<div class="highlights">
  <div class="highlight">
  <h2>Laravel & GraphQL</h2>
    <p>
      Lighthouse dramatically reduces the amount of boilerplate needed to get a GraphQL project up and running. Many of the familiar concepts from Laravel are converted into Lighthouse directives, so you can reuse existing logic and work the way you are used to.
    </p>
    <p>
      If you already have your models and migrations set up, it only takes minutes to get up and running with Lighthouse. You only need to define a schema to have a fully functional GraphQL server with no additional code.
    </p>
    <p>
      But don't worry, you can extend Lighthouse to fit just about any data requirements. The docs will walk you through what directives are available, how to create your own directives and how to create your own resolvers. 
    </p>
  </div>
  <div class="highlight">

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

  </div>
</div>
<p class="action"><a href="/docs/latest.html" class="nav-link action-button">Get Started  →</a></p>

<br />
<br />
