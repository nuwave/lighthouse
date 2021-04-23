# Eloquent Relationships

Eloquent relationships can be accessed just like any other properties.
This makes it super easy to use in your schema.

Suppose you have defined the following model:

```php
<?php

namespace App\Models;

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

## Defining Relationships

Just like in Laravel, you can define [Eloquent Relationships](https://laravel.com/docs/eloquent-relationships) in your schema.
Lighthouse has got you covered with specialized directives that optimize the Queries for you.

Suppose you want to load a list of posts and associated comments. When you tell
Lighthouse about the relationship, it automatically eager loads the comments when you need them.

### One To One

Use the [@hasOne](../api-reference/directives.md#hasone) directive to define a [one-to-one relationship](https://laravel.com/docs/eloquent-relationships#one-to-one)
between two types in your schema.

```graphql
type User {
  phone: Phone @hasOne
}
```

The inverse can be defined through the [@belongsTo](../api-reference/directives.md#belongsto) directive.

```graphql
type Phone {
  user: User @belongsTo
}
```

### One To Many

Use the [@hasMany](../api-reference/directives.md#hasmany) directive to define a [one-to-many relationship](https://laravel.com/docs/eloquent-relationships#one-to-many).

```graphql
type Post {
  comments: [Comment!]! @hasMany
}
```

Again, the inverse is defined with the [@belongsTo](../api-reference/directives,md#belongsTo) directive.

```graphql
type Comment {
  post: Post! @belongsTo
}
```

### Many To Many

While [many-to-many relationships](https://laravel.com/docs/5.7/eloquent-relationships#many-to-many)
are a bit more work to set up in Laravel, defining them in Lighthouse is a breeze.
Use the [@belongsToMany](../api-reference/directives.md#belongstomany) directive to define it.

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

```graphql
type Post {
  author: User! @belongsTo(relation: "user")
}
```

This would work for the following model:

```php
<?php

namespace App\Models;

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
