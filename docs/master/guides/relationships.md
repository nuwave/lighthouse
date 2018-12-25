# Eloquent Relationships

Eloquent relationships can be accessed just like any other properties.
This makes it super easy to use in your schema.

Suppose you have defined the following model:

```php
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
    
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

Just add fields to your type that are named just like the relationships:

```graphql
type Post {
  author: User
  comments: [Comment!]
}
```

This approach is fine if performance is not super critical or if you only fetch a single post.
However, as your queries become larger and more complex, you might want to optimize performance.

## Querying Relationships

Just like in Laravel, you can define [Eloquent Relationships](https://laravel.com/docs/eloquent-relationships) in your schema.
Lighthouse has got you covered with specialized directives that optimize the Queries for you.

Suppose you want to load a list of posts and associated comments. When you tell
Lighthouse about the relationship, it automatically eager loads the comments when you need them.

For special cases, you can use [`@with`](../api-reference/directives.md#with) to eager-load a relation
without returning it directly.

### One To One

Use the [@hasOne](../api-reference/directives.md#hasOne) directive to define a [one-to-one relationship](https://laravel.com/docs/eloquent-relationships#one-to-one)
between two types in your schema.

```graphql
type User {
  phone: Phone @hasOne
}
```

The inverse can be defined through the [@belongsTo](../api-reference/directives#belongsTo) directive.

```graphql
type Phone {
  user: User @belongsTo
}
```

### One To Many

Use the [@hasMany](../api-reference/directives#hasMany) directive to define a [one-to-many relationship](https://laravel.com/docs/eloquent-relationships#one-to-many).

```graphql
type Post {
  comments: [Comment!]! @hasMany
}
```

Again, the inverse is defined with the [@belongsTo](../api-reference/directives#belongsTo) directive.

```graphql
type Comment {
  post: Post! @belongsTo
}
```

### Many To Many

While [many-to-many relationships](https://laravel.com/docs/5.7/eloquent-relationships#many-to-many)
are a bit more work to set up in Laravel, defining them in Lighthouse is a breeze.
Use the [@belongsToMany](../api-reference/directives#belongsToMany) directive to define it.

```graphql
type User {
  roles: [Role!]! @belongsToMany
}
```

The inverse works the same.

```graphql
type Role {
  users: [User!]! @belongsToMany
}
```

## Renaming Relations

When you define a relation, Lighthouse assumes that the field and the relationship
method have the same name. If you need to name your field differently, you have to
specify the name of the method.

```
type Post {
  author: User! @belongsTo(relation: "user")
}
```

This would work for the following model:

```php
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model 
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

## Mutating Relationships

Lighthouse allows you to create, update or delete your relationships in
a single mutation.

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

By default, all mutations are wrapped in a database transaction.
If any of the nested operations fail, the whole mutation is aborted
and no changes are written to the database.
You can change this setting [in the configuration](../getting-started/configuration.md).

### Belongs To

You can allow the user to attach a `BelongsTo` relationship by defining
an argument that is named just like the underlying relationship method.

```graphql
type Mutation {
  createPost(input: CreatePostInput!): Post @create(flatten: true)
}

input CreatePostInput {
  title: String!
  author: ID
}
```

Just pass the ID of the model you want to associate.

```graphql
mutation {
  createPost(input: {
    title: "My new Post"
    author: 123
  }){
    id
    author {
      name
    }
  }
}
```

Lighthouse will detect the relationship and attach it.

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

You may also allow the user to change or remove a relation.

```graphql
type Mutation {
  updatePost(input: UpdatePostInput!): Post @update(flatten: true)
}

input UpdatePostInput {
  title: String
  author: ID
}
```

If you want to remove a relation, simply set it to `null`,

```graphql
mutation {
  updatePost(input: {
    title: "An updated title"
    author: null
  }){
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
    "updatePost": {
      "title": "An updated title",
      "author": null
    }
  }
}
```
