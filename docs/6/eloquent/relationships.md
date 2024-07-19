# Eloquent Relationships

Just like in Laravel, you can define [Eloquent Relationships](https://laravel.com/docs/eloquent-relationships) in your schema.

Suppose you have defined the following model:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Post extends Model
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

Because Laravel relationships can be accessed just like regular properties on your model,
the default field resolver will work just fine.

## Avoiding the N+1 performance problem

When accessing Eloquent relationships as properties, the relationship data is "lazy loaded".
This means the relationship data is not actually loaded until you first access the property.

This leads to a common performance pitfall that comes with the nested nature of GraphQL queries:
the so-called N+1 query problem. [Learn more](../performance/n-plus-one.md).

When you decorate your relationship fields with Lighthouse's built-in relationship
directives, queries are automatically combined through a technique called _batch loading_.
That means you get fewer database requests and better performance without doing much work.

> Batch loading might not provide ideal performance for all use cases. You can turn
> it off by setting the config option `batchload_relations` to `false`.

## One To One

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

## One To Many

Use the [@hasMany](../api-reference/directives.md#hasmany) directive to define a [one-to-many relationship](https://laravel.com/docs/eloquent-relationships#one-to-many).

```graphql
type Post {
  comments: [Comment!]! @hasMany
}
```

Again, the inverse is defined with the [@belongsTo](../api-reference/directives.md#belongsto) directive.

```graphql
type Comment {
  post: Post! @belongsTo
}
```

## Many To Many

While [many-to-many relationships](https://laravel.com/docs/eloquent-relationships#many-to-many)
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

## Has One Through

Use the [@hasOneThrough](../api-reference/directives.md#hasonethrough) directive to define a [has-one-through relationship](https://laravel.com/docs/eloquent-relationships#has-one-through).

```graphql
type Mechanic {
  carOwner: Owner! @hasOneThrough
}
```

## Has Many Through

Use the [@hasManyThrough](../api-reference/directives.md#hasmanythrough) directive to define a [has-many-through relationship](https://laravel.com/docs/eloquent-relationships#has-many-through).

```graphql
type Project {
  deployments: [Deployment!]! @hasManyThrough
}
```

## Renaming relations

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
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Post extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```
