# Tutorial

This is an introductory tutorial for building a GraphQL server with Lighthouse.
While we try to keep it beginner friendly, we recommend familiarizing yourself
with [GraphQL](https://graphql.org/) and [Laravel](https://laravel.com/) first.

## What is GraphQL?

GraphQL is a query language for APIs and a runtime for fulfilling those queries with your existing data.
GraphQL provides a complete and understandable description of the data in your API,
gives clients the power to ask for exactly what they need and nothing more,
makes it easier to evolve APIs over time, and enables powerful developer tools.

<div align="center">
  <img src="./assets/tutorial/playground.png">  
  <small>GraphQL Playground</small>
</div>

GraphQL has been released only as a [*specification*](https://facebook.github.io/graphql/).
This means that GraphQL is in fact not more than a long document that describes in detail
the behaviour of a GraphQL server. 

So, GraphQL has its own type system that’s used to define the schema of an API.
The syntax for writing schemas is called [Schema Definition Language](https://www.prisma.io/blog/graphql-sdl-schema-definition-language-6755bcb9ce51/) or short **SDL**.

Here is an example how we can use the SDL to define a type called `Person` and its
relation to another type `Post`.

```graphql
type Person {
  name: String!
  age: Int!
  posts: [Post!]!
}

type Post {
  title: String!
  author: Person!
}
```

Note that we just created a one-to-many relationship between `Person` and `Post`.
The type `Person` has a field `posts` that returns a list of `Post` types.

We also defined the inverse relationship from `Post` to `Person` through the `author` field.

::: tip NOTE
 This short intro is a compilation from many sources, all credits goes to the original authors.
 - [https://graphql.org](https://graphql.org)
 - [https://howtographql.com](https://howtographql.com)
:::

## What is Lighthouse?

Lighthouse is a PHP package that allows you to serve a GraphQL endpoint from your Laravel application.

It greatly reduces the boilerplate required to create a schema,
integrates well with any Laravel project,
and is highly customizable giving you full control over your data.

The whole process of building your own GraphQL server can be described in 3 steps:

1. Define the shape of your data using the Schema Definition Language
1. Use pre-built directives to bring your schema to life
1. Extend Lighthouse with custom functionality where you need it

**... and you are done!**

<div align="center">
  <img src="./assets/tutorial/flow.png">  
  <small>The role of GraphQL in your application</small>
</div>

## Agenda

In this tutorial we will create a GraphQL API for a simple Blog from scratch with:

- Laravel 5.7
- Lighthouse 2.x
- Laravel GraphQL Playground
- MySQL

::: tip
You can download the source code for this tutorial at [https://github.com/nuwave/lighthouse-tutorial](https://github.com/nuwave/lighthouse-tutorial)
:::

## Installation

Create a new Laravel project.
You can use an existing project if you like, but you may have to adapt as we go along.
Read more about [installing Laravel](https://laravel.com/docs/#installing-laravel).

    laravel new lighthouse-tutorial

In this tutorial we will use [Laravel GraphQL Playground](https://github.com/mll-lab/laravel-graphql-playground)
as an IDE for GraphQL queries. It's like Postman for GraphQL, but with super powers.
Of course, we will use Lighthouse as the GraphQL Server.

    composer require nuwave/lighthouse mll-lab/laravel-graphql-playground

Then publish the configurations files and the default schema.

```bash
# lighthouse
php artisan vendor:publish --provider="Nuwave\Lighthouse\LighthouseServiceProvider"

# playground
php artisan vendor:publish --provider="MLL\GraphQLPlayground\GraphQLPlaygroundServiceProvider"
```

The default schema will be published to `graphql/schema.graphql`.

Consult the [Laravel docs on database configuration](https://laravel.com/docs/database#configuration)
and ensure you have a working database set up.

Run database migrations to create the `users` table:

    php artisan migrate

Seed the database with some fake users:

    php artisan tinker
    factory('App\User', 10)->create();

Now you are ready to start your server.
Use [Homestead](https://laravel.com/docs/homestead),
[Valet](https://laravel.com/docs/5.7/valet) or:

    php artisan serve

To make sure everything is working, access Laravel GraphQL Playground on http://127.0.0.1:8000/graphql-playground
and try the following query:

```graphql
{
  user(id: 1) {
    id
    name
    email
  }
}
```

Now, let's move on and create a GraphQL API for our Blog.

## The Models

One user can publish many posts, and each post has many comments from anonymous users.

<div align="center">
  <img src="./assets/tutorial/model.png">  
  <p><small>Database relations diagram</small></p>
</div>

This first part is pure Laravel, we will add the GraphQL part afterwards.

Begin by defining models and migrations for your posts and comments

    php artisan make:model -m Post

```php
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
}
```

```php
<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePostsTable extends Migration
{
    public function up()
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('title');
            $table->string('content');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('posts');
    }
}
```

    php artisan make:model -m Comment

```php
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
```

```php
<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCommentsTable extends Migration
{
    public function up()
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('post_id');
            $table->string('reply');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('comments');
    }
}
```

Remember to run the migrations:

    php artisan migrate

Finally, add the `posts` relation to `app/User.php`

```php
<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name', 'email', 'password',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
```

## The Magic

Let's edit `routes/graphql/schema.graphql` and define our blog schema,
based on the Eloquent Models we created.

First, we define the root Query type which contains two different queries for retrieving posts.

```graphql
type Query{
    posts: [Post!]! @all
    post(id: Int! @eq): Post @find
}
```

The way that Lighthouse knows how to resolve the queries is a combination of convention-based
naming - the type name `Post` is also the name of our Model - and the use of server-side directives.

- [`@all`](../api-reference/directives.md#all) just gets you a list of all `Post` models
- [`@find`](../api-reference/directives.md#find) and [`@eq`](../api-reference/directives.md#eq)
  are combined to retrieve a single `Post` by its ID


Then, we add additional type definitions that clearly define the shape of our data. 

```graphql
type Query{
    posts: [Post!]! @all
    post (id: Int! @eq): Post @find
}

type User {
    id: ID!
    name: String!
    email: String!
    created_at: DateTime!
    updated_at: DateTime!
    posts: [Post!]! @hasMany
}

type Post {
    id: ID!
    title: String!
    content: String!
    user: User! @belongsTo
    comments: [Comment!]! @hasMany
}

type Comment{
    id: ID!
    reply: String!
    post: Post! @belongsTo
}
```

Just like in Eloquent, we express the relationship between our types using the
[`@belongsTo`](../api-reference/directives.md#belongsto) and [`@hasMany`](../api-reference/directives.md#hasmany) directives.

## The Final Test

Insert some fake data into your database,
you can use [Laravel seeders](https://laravel.com/docs/seeding) for that.

Visit [http://127.0.0.1:8000/graphql-playground](http://127.0.0.1:8000/graphql-playground) and try the following query:

```graphql
{
  posts {
    id
    title
    user {
      id
      name
    }
    comments {
      id
      reply
    }
  }
}
```

You should get a list of all the posts in your database,
together will all the comments and user information defined upon.

I hope this example shows a taste of the power of GraphQL
and how Lighthouse makes it easy to build your own server with Laravel. 

## Next Steps

The app you just build might use some more features.
Here are a few ideas on what you might add to learn more about Lighthouse.

- [Add pagination to your fields](../api-reference/directives.md#paginate)
- [Create and update posts and comments](../the-basics/fields.md#mutate-data)
- [Validate the inputs that are sent to your server](../guides/validation.md)
