# Directives

## @aggregate

```graphql
"""
Returns an aggregate of a column in a given relationship or model.
"""
directive @aggregate(
  """
  The column to aggregate.
  """
  column: String!

  """
  The aggregate function to compute.
  """
  function: AggregateFunction!

  """
  The relationship with the column to aggregate.
  Mutually exclusive with `model` and `builder`.
  """
  relation: String

  """
  The model with the column to aggregate.
  Mutually exclusive with `relation` and `builder`.
  """
  model: String

  """
  Point to a function that provides a Query Builder instance.
  Consists of two parts: a class name and a method name, seperated by an `@` symbol.
  If you pass only a class name, the method name defaults to `__invoke`.
  Mutually exclusive with `relation` and `model`.
  """
  builder: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) on FIELD_DEFINITION

"""
Options for the `function` argument of `@aggregate`.
"""
enum AggregateFunction {
  """
  Return the average value.
  """
  AVG

  """
  Return the sum.
  """
  SUM

  """
  Return the minimum.
  """
  MIN

  """
  Return the maximum.
  """
  MAX
}
```

If all you need is counting, use [@count](#count).

To retrieve the aggregate of a column on a root field, reference a `model`:

```graphql
type Query {
  totalDownloads: Int!
    @aggregate(model: "Song", column: "downloads", function: SUM)
}
```

To retrieve the aggregate of a column in related models, reference the `relation`:

```graphql
type Album {
  rating: Float! @aggregate(relation: "songs", column: "rating", function: AVG)
}
```

You may combine filters and scopes:

```graphql
type Query {
  mostListened(genre: String @eq): Int!
    @aggregate(
      model: "Song"
      column: "listen_count"
      function: MAX
      scope: ["published"]
    )
}
```

## @all

```graphql
"""
Fetch all Eloquent models and return the collection as the result.
"""
directive @all(
  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.
  Mutually exclusive with `builder`.
  """
  model: String

  """
  Point to a function that provides a Query Builder instance.
  Consists of two parts: a class name and a method name, seperated by an `@` symbol.
  If you pass only a class name, the method name defaults to `__invoke`.
  Mutually exclusive with `model`.
  """
  builder: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) on FIELD_DEFINITION
```

This assumes your model has the same name as the type you are returning and is defined
in the default model namespace `App`. [You can change this configuration](../getting-started/configuration.md).

```graphql
type Query {
  users: [User!]! @all
}
```

If you need to use a different model for a single field, you can pass a class name as the `model` argument.

```graphql
type Query {
  posts: [Post!]! @all(model: "App\\Blog\\BlogEntry")
}
```

## @async

```graphql
"""
Defer the execution of mutations to [queued jobs](https://laravel.com/docs/queues).

This directive must only be used on fields of the root mutation type.
When the field is executed, a `Nuwave\Lighthouse\Async\AsyncMutation` job is dispatched
and the value `true` is returned - thus the fields return type must be `Boolean!`.

Once a [queue worker](https://laravel.com/docs/queues#running-the-queue-worker) picks up the job,
it will actually execute the underlying field resolver.
Errors that occur during execution are reported through the Laravel exception handler.
The handlers in the `config/lighthouse.php` option `error_handlers` are not called.
"""
directive @async(
  """
  Name of the queue to dispatch the job on.
  If not specified, jobs will be dispatched to the default queue.
  See https://laravel.com/docs/queues#customizing-the-queue-and-connection.
  """
  queue: String
) on FIELD_DEFINITION
```

## @auth

```graphql
"""
Return the currently authenticated user as the result of a query.
"""
directive @auth(
  """
  Specify which guards to use, e.g. ["api"].
  When not defined, the default from `lighthouse.php` is used.
  """
  guards: [String!]
) on FIELD_DEFINITION
```

```graphql
type Query {
  me: User @auth
}
```

If you need to use a guard besides the default to resolve the authenticated user,
you can pass the guard name as the `guards` argument.

```graphql
type Query {
  me: User @auth(guards: ["api"])
}
```

When multiple guards are passed, the first one that returns a user is used.

```graphql
union Authenticable = Admin | User

type Query {
  me: Authenticable @auth(guards: ["api", "admin"])
}
```

## @belongsTo

```graphql
"""
Resolves a field through the Eloquent `BelongsTo` relationship.
"""
directive @belongsTo(
  """
  Specify the relationship method name in the model class,
  if it is named different from the field in the schema.
  """
  relation: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) on FIELD_DEFINITION
```

It assumes both the field and the relationship method to have the same name.

```graphql
type Post {
  author: User @belongsTo
}
```

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Post extends Model
{
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

The directive accepts an optional `relation` argument if your relationship method
has a different name than the field.

```graphql
type Post {
  user: User @belongsTo(relation: "author")
}
```

## @belongsToMany

```graphql
"""
Resolves a field through the Eloquent `BelongsToMany` relationship.
"""
directive @belongsToMany(
  """
  Specify the relationship method name in the model class,
  if it is named different from the field in the schema.
  """
  relation: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]

  """
  Allows to resolve the relation as a paginated list.
  """
  type: BelongsToManyType

  """
  Allow clients to query paginated lists without specifying the amount of items.
  Overrules the `pagination.default_count` setting from `lighthouse.php`.
  Setting this to `null` means clients have to explicitly ask for the count.
  """
  defaultCount: Int

  """
  Limit the maximum amount of items that clients can request from paginated lists.
  Overrules the `pagination.max_count` setting from `lighthouse.php`.
  Setting this to `null` means the count is unrestricted.
  """
  maxCount: Int

  """
  Specify a custom type that implements the Edge interface
  to extend edge object.
  Only applies when using Relay style "connection" pagination.
  """
  edgeType: String
) on FIELD_DEFINITION

"""
Options for the `type` argument of `@belongsToMany`.
"""
enum BelongsToManyType {
  """
  Offset-based pagination, similar to the Laravel default.
  """
  PAGINATOR

  """
  Offset-based pagination like the Laravel "Simple Pagination", which does not count the total number of records.
  """
  SIMPLE

  """
  Cursor-based pagination, compatible with the Relay specification.
  """
  CONNECTION
}
```

### Basic Usage

The field and the relationship method are assumed to have the same name.

```graphql
type User {
  roles: [Role!]! @belongsToMany
}
```

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class User extends Model
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
```

### Rename Relation

The directive accepts an optional `relation` argument if your relationship method
has a different name than the field.

```graphql
type User {
  jobs: [Role!]! @belongsToMany(relation: "roles")
}
```

### Retrieving Intermediate Table Columns

You may want to allow accessing data that describes the relation between the models
and is stored in the intermediate table - see [retrieving intermediate table columns in Laravel](https://laravel.com/docs/eloquent-relationships#retrieving-intermediate-table-columns).

Just like in Laravel, you can access the `pivot` attribute on the models (or its alias).
Even though this attribute is always present when querying the model through the relation,
it may not be present when reaching the node through another path in the schema, so it is
recommended to define the field as nullable (no `!`).

The following example assumes the intermediate table between `User` and `Role` defines
a column `meta`.

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class User extends Model
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withPivot('meta');
    }
}

final class Role extends Model
{
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('meta');
    }
}
```

```graphql
type User {
  id: ID!
  roles: [Role!]! @belongsToMany
  pivot: RoleUserPivot
}

type Role {
  id: ID!
  users: [Users!]! @belongsToMany
  pivot: RoleUserPivot
}

type RoleUserPivot {
  meta: String
}
```

When using the `type` argument with pagination style `CONNECTION`, you may create your own [edge type](https://facebook.github.io/relay/graphql/connections.htm#sec-Edge-Types)
that either contains the attributes of the intermediate table or contains a `pivot` field with the corresponding type.

The custom edge type must contain at least the following two fields:

- `cursor: String!`
- `node: <RelatedModel>!` (in this case `node: Role!`)

It is expected to be named `<RelatedModel>Edge` (in this case `RoleEdge`).
Assuming the intermediate table defines a column `meta`, the definition could look like this:

```graphql
type User {
  roles: [Role!]! @belongsToMany(type: CONNECTION)
}

type RoleEdge {
  node: Role!
  cursor: String!
  meta: String
}
```

As an alternative, you can also expose the `pivot` field on the edge:

```graphql
type User {
  roles: [Role!]! @belongsToMany(type: CONNECTION)
}

type UserRole {
  meta: String
}

type RoleEdge {
  node: Role!
  cursor: String!
  pivot: UserRole
}
```

## @broadcast

```graphql
"""
Broadcast the results of a mutation to subscribed clients.
"""
directive @broadcast(
  """
  Name of the subscription that should be retriggered as a result of this operation.
  """
  subscription: String!

  """
  Specify whether or not the job should be queued.
  This defaults to the global config option `lighthouse.subscriptions.queue_broadcasts`.
  """
  shouldQueue: Boolean
) repeatable on FIELD_DEFINITION
```

[Read more about subscriptions](../subscriptions/getting-started.md)

The `subscription` argument must reference the name of a subscription field.

```graphql
type Mutation {
  createPost(input: CreatePostInput!): Post
    @broadcast(subscription: "postCreated")
}
```

You may override the default queueing behaviour from the configuration by
passing the `shouldQueue` argument.

```graphql
type Mutation {
  updatePost(input: UpdatePostInput!): Post
    @broadcast(subscription: "postUpdated", shouldQueue: false)
}
```

## @builder

```graphql
"""
Manipulate the query builder with a method.
"""
directive @builder(
  """
  Reference a method that is passed the query builder.
  Consists of two parts: a class name and a method name, separated by an `@` symbol.
  If you pass only a class name, the method name defaults to `__invoke`.
  """
  method: String!

  """
  Pass a value to the method as the second argument after the query builder.
  Only used when the directive is added on a field.
  """
  value: BuilderValue
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION | FIELD_DEFINITION

"""
Any constant literal value: https://graphql.github.io/graphql-spec/draft/#sec-Input-Values
"""
scalar BuilderValue
```

You must point to a `method` which will receive the builder instance
and can apply additional constraints to the query.

> This directive only works if the field resolver passes its builder through a call to `$resolveInfo->enhanceBuilder()`.
> Built-in field resolver directives that query the database do this, such as [@all](#all) or [@hasMany](#hasmany).

When used on an argument, the method is only called when the argument is specified (may be `null`),
and its value is passed as the second parameter.
When used on a field, the method is always called, and if the `value` argument is defined,
it is passed as the second parameter.

```graphql
type Query {
    users(
        minimumHighscore: Int @builder(method: "App\MyClass@minimumHighscore")
    ): [User!]! @all
    highrankedUsers: [User!]! @all @builder(method: "App\MyClass@minimumHighscore", value: 1000)
}
```

```php
use Illuminate\Database\Eloquent\Builder;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final class MyClass
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function minimumHighscore(Builder $builder, ?int $minimumHighscore, mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Builder
    {
        if (! $minimumHighscore) return $builder;
        return $builder->whereHas('game', static fn (Builder $builder): Builder => $builder->where('score', '>', $minimumHighscore));
    }
}
```

## @cache

```graphql
"""
Cache the result of a resolver.
"""
directive @cache(
  """
  Set the duration it takes for the cache to expire in seconds.
  If not given, the result will be stored forever.
  """
  maxAge: Int

  """
  Limit access to cached data to the currently authenticated user.
  When the field is accessible by guest users, this will not have
  any effect, they will access a shared cache.
  """
  private: Boolean = false
) on FIELD_DEFINITION
```

You can find usage examples of this directive in [the caching docs](../performance/caching.md).

## @cacheControl

```graphql
"""
Influences the HTTP `Cache-Control` headers of the response.
"""
directive @cacheControl(
  """
  The maximum amount of time the field's cached value is valid, in seconds.
  0 means the field is not cacheable.
  Mutually exclusive with `inheritMaxAge = true`.
  """
  maxAge: Int! = 0

  """
  Is the value specific to a single user?
  """
  scope: CacheControlScope! = PUBLIC

  """
  Should the field inherit the `maxAge` of its parent field instead of using the default `maxAge`?
  Mutually exclusive with `maxAge`.
  """
  inheritMaxAge: Boolean! = false
) on FIELD_DEFINITION | OBJECT | INTERFACE | UNION

"""
Options for the `scope` argument of `@cacheControl`.
"""
enum CacheControlScope {
  """
  The value is the same for each user.
  """
  PUBLIC

  """
  The value is specific to a single user.
  """
  PRIVATE
}
```

Find usage examples of this directive in [the caching docs](../performance/caching.md#http-cache-control-header).

## @cacheKey

```graphql
"""
Specify the field to use as a key when creating a cache.
"""
directive @cacheKey on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION | FIELD_DEFINITION
```

You can find usage examples of this directive in [the caching docs](../performance/caching.md#cache-key).

## @can

Deprecated. Use the [@can\* family of directives](#can-family-of-directives) instead.

## @can\* family of directives

All `@can*` directives have common arguments. These arguments specify how gates are checked and what to do if the user is not authorized.
Each directive has its own set of arguments that specify what to check against.

```graphql
"""
The ability to check permissions for.
"""
ability: String!

"""
Pass along the client given input data as arguments to `Gate::check`.
"""
injectArgs: Boolean! = false

"""
Statically defined arguments that are passed to `Gate::check`.

You may pass arbitrary GraphQL literals,
e.g.: [1, 2, 3] or { foo: "bar" }
"""
args: CanArgs

"""
Action to do if the user is not authorized.
"""
action: CanAction! = EXCEPTION_PASS

"""
Value to return if the user is not authorized and `action` is `RETURN_VALUE`.
"""
returnValue: CanArgs
"""
```

Types are specified as:

```graphql
"""
Any constant literal value: https://graphql.github.io/graphql-spec/draft/#sec-Input-Values
"""
scalar CanArgs

enum CanAction {
  """
  Pass exception to the client.
  """
  EXCEPTION_PASS

  """
  Throw generic "not authorized" exception to conceal the real error.
  """
  EXCEPTION_NOT_AUTHORIZED

  """
  Return the value specified in `returnValue` argument to conceal the real error.
  """
  RETURN_VALUE
}
```

You can find usage examples of these directives in [the authorization docs](../security/authorization.md#restrict-fields-through-policies).

### @canFind

```graphql
"""
Check a Laravel Policy to ensure the current user is authorized to access a field.

Query for specific model instances to check the policy against, using primary key(s) from specified argument.
"""
directive @canFind(
  """
  Specify the name of the field argument that contains its primary key(s).

  You may pass the string in dot notation to use nested inputs.
  """
  find: String!

  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.
  """
  model: String

  """
  Should the query fail when the models of `find` were not found?
  """
  findOrFail: Boolean! = true

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) repeatable on FIELD_DEFINITION
```

### canRoot

```graphql
"""
Check a Laravel Policy to ensure the current user is authorized to access a field.

Check the policy against the root model.
"""
directive @canRoot(
  """
  The model name to check against.
  """
  model: String
) repeatable on FIELD_DEFINITION
```

### @canQuery

```graphql
"""
Check a Laravel Policy to ensure the current user is authorized to access a field.

Query for specific model instances to check the policy against, using arguments
with directives that add constraints to the query builder, such as `@eq`.
"""
directive @canQuery(
  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) repeatable on FIELD_DEFINITION
```

### @canResolved

```graphql
"""
Check a Laravel Policy to ensure the current user is authorized to access a field.

Check the policy against the model instances returned by the field resolver.
Only use this if the field does not mutate data, it is run before checking.
"""
directive @canResolved repeatable on FIELD_DEFINITION
```

### @canRoot

```graphql
"""
Check a Laravel Policy to ensure the current user is authorized to access a field.

Check the policy against the root object.
"""
directive @canRoot repeatable on FIELD_DEFINITION
```

## @clearCache

```graphql
"""
Clear a resolver cache by tags.
"""
directive @clearCache(
  """
  Name of the parent type of the field to clear.
  """
  type: String!

  """
  Source of the parent ID to clear.
  """
  idSource: ClearCacheIdSource

  """
  Name of the field to clear.
  """
  field: String
) repeatable on FIELD_DEFINITION

"""
Options for the `idSource` argument of `@clearCache`.

Exactly one of the fields must be given.
"""
input ClearCacheIdSource {
  """
  Path of an argument the client passes to the field `@clearCache` is applied to.
  """
  argument: String

  """
  Path of a field in the result returned from the field `@clearCache` is applied to.
  """
  field: String
}
```

You can find usage examples of this directive in [the caching docs](../performance/caching.md#clear-cache).

## @complexity

```graphql
"""
Customize the calculation of a fields complexity score before execution.
"""
directive @complexity(
  """
  Reference a function to customize the complexity score calculation.
  Consists of two parts: a class name and a method name, seperated by an `@` symbol.
  If you pass only a class name, the method name defaults to `__invoke`.
  """
  resolver: String!
) on FIELD_DEFINITION
```

You can provide your own function to calculate complexity.
[Read More about query complexity analysis](https://webonyx.github.io/graphql-php/security/#query-complexity-analysis)

```graphql
type Query {
  posts(includeFullText: Boolean): [Post!]!
    @complexity(
      resolver: "App\\GraphQL\\Security\\ComplexityAnalyzer@userPosts"
    )
}
```

A custom complexity function may look like the following,
refer to the [complexity function signature](resolvers.md#complexity-function-signature).

```php
namespace App\GraphQL\Security;

final class ComplexityAnalyzer
{
    public function userPosts(int $childrenComplexity, array $args): int
    {
        $postComplexity = ($args['includeFullText'] ?? false)
            ? 3
            : 2;

        return 1 + ($childrenComplexity * $postComplexity);
    }
```

## @convertEmptyStringsToNull

```graphql
"""
Replaces `""` with `null`.
"""
directive @convertEmptyStringsToNull on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION | FIELD_DEFINITION
```

Use this when there is no meaningful distinction between an empty string and null.

```graphql
type Mutation {
  createPost(title: String @convertEmptyStringsToNull): Post!
}
```

Usage on a field applies the conversion recursively to all inputs.

```graphql
type Mutation {
  createPost(input: CreatePostInput!): Post! @convertEmptyStringsToNull
}
```

Non-nullable arguments will _not_ be converted when this directive is used on a field,
but will be converted when it is used directly on the argument.

```graphql
type Mutation {
  createPost(
    willBeConvertedBecauseExplicitlyMarked: String! @convertEmptyStringsToNull
    willNotBeConvertedToMaintainInvariants: String!
  ): Post! @convertEmptyStringsToNull
}
```

If you want this for all your fields, consider adding this directive to your
global field middleware in `lighthouse.php`:

```php
    'field_middleware' => [
        \Nuwave\Lighthouse\Schema\Directives\ConvertEmptyStringsToNullDirective::class,
        ...
    ],
```

## @count

```graphql
"""
Returns the count of a given relationship or model.
"""
directive @count(
  """
  The relationship to count.
  Mutually exclusive with `model`.
  """
  relation: String

  """
  The model to count.
  Mutually exclusive with `relation`.
  """
  model: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]

  """
  Count only rows where the given columns are non-null.
  `*` counts every row.
  """
  columns: [String!]! = ["*"]

  """
  Should exclude duplicated rows?
  """
  distinct: Boolean! = false
) on FIELD_DEFINITION
```

Specify the name of the model to count when using this directive on a root query:

```graphql
type Query {
  categoryCount: Int! @count(model: "Category")
}
```

You can also count relations:

```graphql
type User {
  id: ID!
  likeCount: Int! @count(relation: "likes")
}
```

## @create

```graphql
"""
Create a new Eloquent model with the given arguments.
"""
directive @create(
  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.
  """
  model: String

  """
  Specify the name of the relation on the parent model.
  This is only needed when using this directive as a nested arg
  resolver and if the name of the relation is not the arg name.
  """
  relation: String
) on FIELD_DEFINITION | ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

Use it on a root mutation field that returns an instance of the Model.

```graphql
type Mutation {
  createPost(title: String!): Post! @create
}
```

If you are using a single input object as an argument, you must tell Lighthouse
to spread out the nested values before applying it to the resolver.

```graphql
type Mutation {
  createPost(input: CreatePostInput! @spread): Post! @create
}

input CreatePostInput {
  title: String!
}
```

If the name of the Eloquent model does not match the return type of the field,
or is located in a non-default namespace, set it with the `model` argument.

```graphql
type Mutation {
  createPost(title: String!): Post! @create(model: "Foo\\Bar\\MyPost")
}
```

This directive can also be used as a [nested arg resolver](../concepts/arg-resolvers.md).

## @createMany

```graphql
"""
Create multiple new Eloquent models with the given arguments.
"""
directive @createMany(
  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.
  """
  model: String

  """
  Specify the name of the relation on the parent model.
  This is only needed when using this directive as a nested arg
  resolver and if the name of the relation is not the arg name.
  """
  relation: String
) on FIELD_DEFINITION | ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

When used on a field, it must have exactly one argument where the type is a non-null list of input objects.

```graphql
type Mutation {
  createPosts(inputs: [CreatePostInput!]!): [Post!]! @createMany
}

input CreatePostInput {
  title: String!
}
```

## @delete

```graphql
"""
Delete one or more models.
"""
directive @delete(
  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.
  """
  model: String

  """
  Specify the name of the relation on the parent model.
  This is only needed when using this directive as a nested arg
  resolver and if the name of the relation is not the arg name.
  """
  relation: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) on FIELD_DEFINITION | ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

Mutation fields using this directive will return an instance of the deleted Model (or Models).

```graphql
type Mutation {
  deletePost(id: ID! @whereKey): Post! @delete
}
```

You can also delete multiple models at once, for example by a list of IDs or a filter.
Be careful with the filters you offer to avoid accidental mass deletion.
Lighthouse will validate that at least one argument is given.

_In contrast to Laravel mass updates, this does trigger model events._

```graphql
type Mutation {
  deletePosts(ids: [ID!] @whereKey, title: String @eq): [Post!]! @delete
}
```

If the name of the Eloquent model does not match the return type of the field,
or is located in a non-default namespace, set it with the `model` argument.

```graphql
type Mutation {
  deletePost(id: ID! @whereKey): Post @delete(model: "Bar\\Baz\\MyPost")
}
```

This directive can also be used as a [nested arg resolver](../concepts/arg-resolvers.md).

```graphql
type Mutation {
  updateUser(id: ID!, deleteTasks: [ID!]! @delete(relation: "tasks")): User!
    @update
}
```

If the model relates to a single other model through a `HasOne`, `MorphOne`, `BelongsTo` or
`MorphTo` relationship, you can just pass a Boolean instead of an ID, as there is only one
possible model that can be deleted.

```graphql
type Mutation {
  updateTask(id: ID!, deleteUser: Boolean @delete(relation: "user")): Task!
    @update
}
```

## @deprecated

```graphql
"""
Marks an element of a GraphQL schema as no longer supported.
"""
directive @deprecated(
  """
  Explains why this element was deprecated, usually also including a
  suggestion for how to access supported similar data.
  Formatted in [Markdown](https://commonmark.org).
  """
  reason: String = "No longer supported"
) on FIELD_DEFINITION | ENUM_VALUE
```

You can mark fields as deprecated by adding the [@deprecated](#deprecated) directive.
It is recommended to provide a `reason` for the deprecation, as well as a suggestion on
how to move forward.

```graphql
type Query {
  allUsers: [User!]! @deprecated(reason: "Use `users`")
  users: [User!]!
}
```

Deprecated elements are not included in introspection queries by default,
but they can still be queried by clients.

## @drop

```graphql
"""
Ignore the user given value, don't pass it to the resolver.
"""
directive @drop on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

This is useful when you want to deprecate a field, but avoid breaking changes
for clients that still pass the value.

```graphql
type User {
  email: String!
  foo: String @deprecated
}

type Mutation {
  createUser(email: String!, foo: String @drop): User! @create
}
```

## @feature

```graphql
"""
Include the annotated element in the schema depending on a Laravel Pennant feature.
"""
directive @feature(
  """
  The name of the feature to be checked (can be a string or class name).
  """
  name: String!

  """
  Specify what the state of the feature should be for the field to be included.
  """
  when: FeatureState! = ACTIVE
) on FIELD_DEFINITION | OBJECT

"""
Options for the `when` argument of `@feature`.
"""
enum FeatureState {
  """
  Indicates an active feature.
  """
  ACTIVE

  """
  Indicates an inactive feature.
  """
  INACTIVE
}
```

Requires the installation of [Laravel Pennant](https://laravel.com/docs/pennant)
and manual registration of the service provider in `config/app.php`:

```php
'providers' => [
    \Nuwave\Lighthouse\Pennant\PennantServiceProvider::class,
],
```

## @field

```graphql
"""
Assign a resolver function to a field.
"""
directive @field(
  """
  A reference to the resolver function to be used.
  Consists of two parts: a class name and a method name, seperated by an `@` symbol.
  If you pass only a class name, the method name defaults to `__invoke`.
  """
  resolver: String!
) on FIELD_DEFINITION
```

> This directive is unnecessary if you use the default namespaces, see [resolver precedence](../the-basics/fields.md#resolver-precedence).

Pass a class and a method to the `resolver` argument and separate them with an `@` symbol.
If you pass only a class name, the method name defaults to `__invoke`.

```graphql
type Mutation {
  createPost(title: String!): Post!
    @field(resolver: "App\\GraphQL\\Mutations\\PostMutator@create")
}
```

You can take advantage of the default namespaces that are defined in the [configuration](../getting-started/configuration.md),
The following will look for a class in `App\GraphQL\Queries` by default.

```graphql
type Query {
  usersTotal: Int @field(resolver: "Statistics@usersTotal")
}
```

Be aware that resolvers are not limited to root fields. A resolver can be used for basic tasks
such as transforming the value of scalar fields, e.g. reformat a date.

```graphql
type User {
  created_at: String!
    @field(resolver: "App\\GraphQL\\Types\\UserType@created_at")
}
```

## @find

```graphql
"""
Find a model based on the arguments provided.
"""
directive @find(
  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.
  """
  model: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) on FIELD_DEFINITION
```

```graphql
type Query {
  userById(id: ID! @whereKey): User @find
}
```

This throws when more than one result is returned.
Use [@first](#first) if the query constraints do not ensure uniqueness.

If your model does not sit in the default namespace, you can overwrite it.

```graphql
type Query {
  userById(id: ID! @whereKey): User @find(model: "App\\Authentication\\User")
}
```

## @first

```graphql
"""
Get the first query result from a collection of Eloquent models.
"""
directive @first(
  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.
  """
  model: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) on FIELD_DEFINITION
```

Other than [@find](#find), this will not throw an error if more than one item is in the collection.

```graphql
type Query {
  userByFirstName(first_name: String! @eq): User @first
}
```

If your model does not sit in the default namespace, you can overwrite it.

```graphql
type Query {
  userByFirstName(first_name: String! @eq): User
    @first(model: "App\\Authentication\\User")
}
```

## @forceDelete

```graphql
"""
Permanently remove one or more soft deleted models.
"""
directive @forceDelete(
  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.
  """
  model: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) on FIELD_DEFINITION
```

Use it on a root mutation field that returns an instance of the Model.

```graphql
type Mutation {
  forceDeletePost(id: ID! @whereKey): Post @forceDelete
}
```

Works very similar to the [@delete](#delete) directive.

## @enum

```graphql
"""
Assign an internal value to an enum key.
When dealing with the Enum type in your code,
you will receive the defined value instead of the string key.
"""
directive @enum(
  """
  The internal value of the enum key.
  """
  value: EnumValue
) on ENUM_VALUE

"""
Any constant literal value: https://graphql.github.io/graphql-spec/draft/#sec-Input-Values
"""
scalar EnumValue
```

```graphql
enum Role {
  ADMIN @enum(value: 1)
  EMPLOYEE @enum(value: 2)
}
```

You do not need this directive if the internal value of each enum key
is an identical string. [Read more about enum types](../the-basics/types.md#enum)

## @eq

```graphql
"""
Add an equal conditional to a database query.
"""
directive @eq(
  """
  Specify the database column to compare.
  Required if the directive is:
  - used on an argument and the database column has a different name
  - used on a field
  """
  key: String

  """
  Provide a value to compare against.
  Exclusively required when this directive is used on a field.
  """
  value: EqValue
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION | FIELD_DEFINITION

"""
Any constant literal value: https://graphql.github.io/graphql-spec/draft/#sec-Input-Values
"""
scalar EqValue
```

> This directive only works if the field resolver passes its builder through a call to `$resolveInfo->enhanceBuilder()`.
> Built-in field resolver directives that query the database do this, such as [@all](#all) or [@hasMany](#hasmany).

```graphql
type User {
  posts(category: String @eq): [Post!]! @hasMany
}
```

If the name of the argument does not match the database column,
pass the actual column name as the `key`.

```graphql
type User {
  posts(category: String @eq(key: "cat")): [Post!]! @hasMany
}
```

You can also use this on a field to define a default filter:

```graphql
type User {
  sportPosts: [Post!]! @hasMany @eq(key: "category", value: "sport")
}
```

## @event

```graphql
"""
Dispatch an event after the resolution of a field.

The event constructor will be called with a single argument:
the resolved value of the field.
"""
directive @event(
  """
  Specify the fully qualified class name (FQCN) of the event to dispatch.
  """
  dispatch: String!
) repeatable on FIELD_DEFINITION
```

For example, you might want to have an event when new orders are placed in a shop:

```graphql
type Mutation {
  placeOrder(items: [CartItems!]!): Order!
    @event(dispatch: "App\\Events\\PlacedOrder")
}
```

The event class must accept an `Order` in the constructor:

```php
final class PlacedOrder
{
    public function __construct(Order $order) { ... }
}
```

## @globalId

```graphql
"""
Converts between IDs/types and global IDs.

When used upon a field, it encodes;
when used upon an argument, it decodes.
"""
directive @globalId(
  """
  Decoding a global id produces a tuple of `$type` and `$id`.
  This setting controls which of those is passed along.
  """
  decode: GlobalIdDecode = ARRAY
) on FIELD_DEFINITION | INPUT_FIELD_DEFINITION | ARGUMENT_DEFINITION

"""
Options for the `decode` argument of `@globalId`.
"""
enum GlobalIdDecode {
  """
  Return an array of `[$type, $id]`.
  """
  ARRAY

  """
  Return just `$type`.
  """
  TYPE

  """
  Return just `$id`.
  """
  ID
}
```

Instead of the original ID, the `id` field will now return a base64-encoded String
that globally identifies the User and can be used for querying the `node` endpoint.

```graphql
type User {
  id: ID! @globalId
  name: String
}
```

The field resolver will receive the decoded version of the passed `id`,
split into type and ID.

```graphql
type Mutation {
  deleteNode(id: ID @globalId): Node
}
```

You may rebind the `\Nuwave\Lighthouse\Support\Contracts\GlobalId` interface to add your
own mechanism of encoding/decoding global ids.

## @guard

```graphql
"""
Run authentication through one or more guards from `config/auth.php`.

This is run per field and may allow unauthenticated
users to still receive partial results.

Used upon an object, it applies to all fields within.
"""
directive @guard(
  """
  Specify which guards to use, e.g. ["web"].
  When not defined, the default from `lighthouse.php` is used.
  """
  with: [String!]
) repeatable on FIELD_DEFINITION | OBJECT
```

Note that [@guard](#guard) does not log in users.
To ensure the user is logged in, add the `AttemptAuthenticate` middleware to your `lighthouse.php` middleware config.

```php
'middleware' => [
    ...

    // Logs in a user if they are authenticated. In contrast to Laravel's 'auth'
    // middleware, this delegates auth and permission checks to the field level.
    \Nuwave\Lighthouse\Http\Middleware\AttemptAuthentication::class,
],
```

A useful pattern is to group fields in an `extend type` to apply [@guard](#guard)
on all of them at once.

```graphql
extend type Query @guard { ... }
```

The [@guard](#guard) directive will be prepended to other directives defined on the fields
and thus executes before them.

```graphql
extend type Query {
  user: User!
    @guard
    @can(ability: "adminOnly")
  ...
}
```

## @hash

```graphql
"""
Use Laravel hashing to transform an argument value.

Useful for hashing passwords before inserting them into the database.
This uses the default hashing driver defined in `config/hashing.php`.
"""
directive @hash on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

The most common use case for this is when dealing with passwords:

```graphql
type Mutation {
  createUser(name: String!, password: String! @hash): User!
}
```

## @hasMany

```graphql
"""
Corresponds to [the Eloquent relationship HasMany](https://laravel.com/docs/eloquent-relationships#one-to-many).
"""
directive @hasMany(
  """
  Specify the relationship method name in the model class,
  if it is named different from the field in the schema.
  """
  relation: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]

  """
  Allows to resolve the relation as a paginated list.
  """
  type: HasManyType

  """
  Allow clients to query paginated lists without specifying the amount of items.
  Overrules the `pagination.default_count` setting from `lighthouse.php`.
  Setting this to `null` means clients have to explicitly ask for the count.
  """
  defaultCount: Int

  """
  Limit the maximum amount of items that clients can request from paginated lists.
  Overrules the `pagination.max_count` setting from `lighthouse.php`.
  Setting this to `null` means the count is unrestricted.
  """
  maxCount: Int

  """
  Specify a custom type that implements the Edge interface
  to extend edge object.
  Only applies when using Relay style "connection" pagination.
  """
  edgeType: String
) on FIELD_DEFINITION

"""
Options for the `type` argument of `@hasMany`.
"""
enum HasManyType {
  """
  Offset-based pagination, similar to the Laravel default.
  """
  PAGINATOR

  """
  Offset-based pagination like the Laravel "Simple Pagination", which does not count the total number of records.
  """
  SIMPLE

  """
  Cursor-based pagination, compatible with the Relay specification.
  """
  CONNECTION
}
```

```graphql
type User {
  posts: [Post!]! @hasMany
}
```

You can return the related models paginated by setting the `type`.

```graphql
type User {
  postsPaginated: [Post!]! @hasMany(type: PAGINATOR)
  postsSimplePaginated: [Post!]! @hasMany(type: SIMPLE)
  postsRelayConnection: [Post!]! @hasMany(type: CONNECTION)
}
```

If the name of the relationship on the Eloquent model differs from the field name,
you can override it by setting `relation`.

```graphql
type User {
  posts: [Post!]! @hasMany(relation: "articles")
}
```

## @hasManyThrough

```graphql
"""
Corresponds to [the Eloquent relationship HasManyThrough](https://laravel.com/docs/eloquent-relationships#has-many-through).
"""
directive @hasManyThrough(
  """
  Specify the relationship method name in the model class,
  if it is named different from the field in the schema.
  """
  relation: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]

  """
  Allows to resolve the relation as a paginated list.
  """
  type: HasManyThroughType

  """
  Allow clients to query paginated lists without specifying the amount of items.
  Overrules the `pagination.default_count` setting from `lighthouse.php`.
  Setting this to `null` means clients have to explicitly ask for the count.
  """
  defaultCount: Int

  """
  Limit the maximum amount of items that clients can request from paginated lists.
  Overrules the `pagination.max_count` setting from `lighthouse.php`.
  Setting this to `null` means the count is unrestricted.
  """
  maxCount: Int

  """
  Specify a custom type that implements the Edge interface
  to extend edge object.
  Only applies when using Relay style "connection" pagination.
  """
  edgeType: String
) on FIELD_DEFINITION

"""
Options for the `type` argument of `@hasManyThrough`.
"""
enum HasManyThroughType {
  """
  Offset-based pagination, similar to the Laravel default.
  """
  PAGINATOR

  """
  Offset-based pagination like the Laravel "Simple Pagination", which does not count the total number of records.
  """
  SIMPLE

  """
  Cursor-based pagination, compatible with the Relay specification.
  """
  CONNECTION
}
```

Usage is the same as [@hasMany](#hasmany).

## @hasOne

```graphql
"""
Corresponds to [the Eloquent relationship HasOne](https://laravel.com/docs/eloquent-relationships#one-to-one).
"""
directive @hasOne(
  """
  Specify the relationship method name in the model class,
  if it is named different from the field in the schema.
  """
  relation: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) on FIELD_DEFINITION
```

```graphql
type User {
  phone: Phone @hasOne
}
```

If the name of the relationship on the Eloquent model differs from the field name,
you can override it by setting `relation`.

```graphql
type User {
  phone: Phone @hasOne(relation: "telephone")
}
```

## @hasOneThrough

```graphql
"""
Corresponds to [the Eloquent relationship HasOneThrough](https://laravel.com/docs/eloquent-relationships#has-one-through).
"""
directive @hasOneThrough(
  """
  Specify the relationship method name in the model class,
  if it is named different from the field in the schema.
  """
  relation: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) on FIELD_DEFINITION
```

```graphql
type Mechanic {
  carOwner: Owner! @hasOneThrough
}
```

If the name of the relationship on the Eloquent model differs from the field name,
you can override it by setting `relation`.

```graphql
type Mechanic {
  carOwner: Owner! @hasOneThrough(relation: "owner")
}
```

## @in

```graphql
"""
Use the client given list value to add an IN conditional to a database query.
"""
directive @in(
  """
  Specify the database column to compare.
  Only required if database column has a different name than the attribute in your schema.
  """
  key: String
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

> This directive only works if the field resolver passes its builder through a call to `$resolveInfo->enhanceBuilder()`.
> Built-in field resolver directives that query the database do this, such as [@all](#all) or [@hasMany](#hasmany).

```graphql
type Query {
  posts(includeIds: [Int!] @in(key: "id")): [Post!]! @paginate
}
```

## @inject

```graphql
"""
Inject a value from the context object into the arguments.
"""
directive @inject(
  """
  A path to the property of the context that will be injected.
  If the value is nested within the context, you may use dot notation
  to get it, e.g. "user.id".
  """
  context: String!

  """
  The target name of the argument into which the value is injected.
  You can use dot notation to set the value at arbitrary depth
  within the incoming argument.
  """
  name: String!
) repeatable on FIELD_DEFINITION
```

This is useful to ensure that the authenticated user's `id` is
automatically used for creating new models and cannot be manipulated.

```graphql
type Mutation {
  createPost(title: String!, content: String!): Post!
    @inject(context: "user.id", name: "user_id")
    @create
}
```

If you are using an Input Object as an argument, you can use dot notation to
set a nested argument.

```graphql
type Mutation {
  createTask(input: CreateTaskInput!): Task!
    @inject(context: "user.id", name: "input.user_id")
    @create
}
```

## @interface

```graphql
"""
Use a custom resolver to determine the concrete type of an interface.
"""
directive @interface(
  """
  Reference to a custom type-resolver function.
  Consists of two parts: a class name and a method name, seperated by an `@` symbol.
  If you pass only a class name, the method name defaults to `__invoke`.
  """
  resolveType: String!
) on INTERFACE
```

Make sure you read the [basics about Interfaces](../the-basics/types.md#interface) before deciding
to use this directive, you probably don't need it.

Set the `resolveType` argument to a function that returns the implementing Object Type.

```graphql
interface Commentable
  @interface(resolveType: "App\\GraphQL\\Interfaces\\Commentable@resolveType") {
  id: ID!
}
```

The function receives the value of the parent field as its single argument and must
return an Object Type. You can get the appropriate Object Type from Lighthouse's type registry.

```php
namespace App\GraphQL\Interfaces;

use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final class Commentable
{
    public function __construct(
        private TypeRegistry $typeRegistry
    ) {}

    /**
     * Decide which GraphQL type a resolved value has.
     *
     * @param  mixed  $root  The value that was resolved by the field. Usually an Eloquent model.
     */
    public function resolveType(mixed $root, GraphQLContext $context, ResolveInfo $resolveInfo): Type
    {
        // TODO implement your own resolver logic - if the default is fine, just delete this class
    }
}
```

## @hide

```graphql
"""
Excludes the annotated element from the schema conditionally.
"""
directive @hide(
  """
  Specify which environments exclude this element, e.g. `["production"]`.
  Compared against the value returned from `app()->environment()`.
  """
  env: [String!]!
) repeatable on FIELD_DEFINITION
```

See [feature toggles](../digging-deeper/feature-toggles.md).

## @lazyLoad

```graphql
"""
Perform a [lazy eager load](https://laravel.com/docs/eloquent-relationships#lazy-eager-loading)
on the relations of a list of models.
"""
directive @lazyLoad(
  """
  The names of the relationship methods to load.
  """
  relations: [String!]!
) repeatable on FIELD_DEFINITION
```

This is often useful when loading relationships with the [@hasMany](#hasmany) directive.

```graphql
type Post {
  comments: [Comment!]! @hasMany @lazyLoad(relations: ["replies"])
}
```

## @like

```graphql
"""
Add a `LIKE` conditional to a database query.
"""
directive @like(
  """
  Specify the database column to compare.
  Required if the directive is:
  - used on an argument and the database column has a different name
  - used on a field
  """
  key: String

  """
  Fixate the positions of wildcards (`%`, `_`) in the LIKE comparison around the
  placeholder `{}`, e.g. `%{}`, `__{}` or `%{}%`.
  If specified, wildcard characters in the client-given input are escaped.
  If not specified, the client can pass wildcards unescaped.
  """
  template: String

  """
  Provide a value to compare against.
  Only used when the directive is added on a field.
  """
  value: String
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION | FIELD_DEFINITION
```

> This directive only works if the field resolver passes its builder through a call to `$resolveInfo->enhanceBuilder()`.
> Built-in field resolver directives that query the database do this, such as [@all](#all) or [@hasMany](#hasmany).

## @limit

```graphql
"""
Allow clients to specify the maximum number of results to return when used on an argument,
or statically limit them when used on a field.

By default, this directive does not influence the number of results the resolver queries internally,
but limits how much of it is returned to clients. Use the `builder` argument to change this.
"""
directive @limit(
  """
  You may set this to `true` if the field uses a query builder,
  then this directive will apply a LIMIT clause to it.
  Typically, this option should only be used for root fields,
  as it may cause wrong results with batched relation queries.
  """
  builder: Boolean! = false
) on ARGUMENT_DEFINITION | FIELD_DEFINITION
```

You may place this on any argument to a field that returns a list of results.

```graphql
type Query {
  users(limit: Int @limit): [User!]!
}
```

Lighthouse will return at most the number of results that the client requested.

```graphql
{
  users(limit: 4) {
    name
  }
}
```

```json
{
  "data": {
    "users": [
      { "name": "Never" },
      { "name": "more" },
      { "name": "than" },
      { "name": "4" }
    ]
  }
}
```

If your field is resolved through a database query, you may add the `builder` argument to apply
an actual `LIMIT` clause to your SQL:

```graphql
type Query {
  users(limit: Int @limit(builder: true)): [User!]! @all
}
```

## @method

```graphql
"""
Resolve a field by calling a method on the parent object.

Use this if the data is not accessible through simple property access or if you
want to pass argument to the method.
"""
directive @method(
  """
  Specify the method of which to fetch the data from.
  Defaults to the name of the field if not given.
  """
  name: String
) on FIELD_DEFINITION
```

This can be useful on models or other classes that have getters:

```graphql
type User {
  mySpecialData: String! @method(name: "getMySpecialData")
}
```

This will call the method `User::purchasedItemsCount()` with the client given arguments.

```graphql
type User {
  purchasedItemsCount(year: Int!, includeReturns: Boolean): Int @method
}
```

Ensure the order of the argument definition matches the parameters of your method.

```php
public function purchasedItemsCount(int $year, ?bool $includeReturns)
```

Lighthouse will always pass down the same number of arguments and default to `null`
if the client passes nothing.

```graphql
{
  user(id: 3) {
    purchasedItemsCount(year: 2017)
  }
}
```

The method will get called like this:

```php
$user->purchasedItemsCount(2017, null)
```

## @model

```graphql
"""
Map a model class to an object type.

This can be used when the name of the model differs from the name of the type.
"""
directive @model(
  """
  The class name of the corresponding model.
  """
  class: String!
) on OBJECT
```

Lighthouse will respect the overwritten model name in its directives.

```graphql
type Post @model(class: "\\App\\BlogPost") {
  title: String!
}
```

## @morphMany

```graphql
"""
Corresponds to [Eloquent's MorphMany-Relationship](https://laravel.com/docs/eloquent-relationships#one-to-many-polymorphic-relations).
"""
directive @morphMany(
  """
  Specify the relationship method name in the model class,
  if it is named different from the field in the schema.
  """
  relation: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]

  """
  Allows to resolve the relation as a paginated list.
  """
  type: MorphManyType

  """
  Allow clients to query paginated lists without specifying the amount of items.
  Overrules the `pagination.default_count` setting from `lighthouse.php`.
  Setting this to `null` means clients have to explicitly ask for the count.
  """
  defaultCount: Int

  """
  Limit the maximum amount of items that clients can request from paginated lists.
  Overrules the `pagination.max_count` setting from `lighthouse.php`.
  Setting this to `null` means the count is unrestricted.
  """
  maxCount: Int

  """
  Specify a custom type that implements the Edge interface
  to extend edge object.
  Only applies when using Relay style "connection" pagination.
  """
  edgeType: String
) on FIELD_DEFINITION

"""
Options for the `type` argument of `@morphMany`.
"""
enum MorphManyType {
  """
  Offset-based pagination, similar to the Laravel default.
  """
  PAGINATOR

  """
  Offset-based pagination like the Laravel "Simple Pagination", which does not count the total number of records.
  """
  SIMPLE

  """
  Cursor-based pagination, compatible with the Relay specification.
  """
  CONNECTION
}
```

```graphql
type Post {
  images: [Image!] @morphMany
}

type Image {
  imageable: Imageable! @morphTo
}

union Imageable = Post | User
```

## @morphOne

```graphql
"""
Corresponds to [Eloquent's MorphOne-Relationship](https://laravel.com/docs/eloquent-relationships#one-to-one-polymorphic-relations).
"""
directive @morphOne(
  """
  Specify the relationship method name in the model class,
  if it is named different from the field in the schema.
  """
  relation: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) on FIELD_DEFINITION
```

```graphql
type Post {
  image: Image! @morphOne
}

type Image {
  imageable: Imageable! @morphTo
}

union Imageable = Post | User
```

## @morphTo

```graphql
"""
Corresponds to [Eloquent's MorphTo-Relationship](https://laravel.com/docs/eloquent-relationships#one-to-one-polymorphic-relations).
"""
directive @morphTo(
  """
  Specify the relationship method name in the model class,
  if it is named different from the field in the schema.
  """
  relation: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) on FIELD_DEFINITION
```

```graphql
type Image {
  imageable: Imageable! @morphTo
}

union Imageable = Post | User
```

## @morphToMany

```graphql
"""
Corresponds to [Eloquent's ManyToMany-Polymorphic-Relationship](https://laravel.com/docs/eloquent-relationships#many-to-many-polymorphic-relations).
"""
directive @morphToMany(
  """
  Specify the relationship method name in the model class,
  if it is named different from the field in the schema.
  """
  relation: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]

  """
  Allows to resolve the relation as a paginated list.
  """
  type: MorphToManyType

  """
  Allow clients to query paginated lists without specifying the amount of items.
  Overrules the `pagination.default_count` setting from `lighthouse.php`.
  Setting this to `null` means clients have to explicitly ask for the count.
  """
  defaultCount: Int

  """
  Limit the maximum amount of items that clients can request from paginated lists.
  Overrules the `pagination.max_count` setting from `lighthouse.php`.
  Setting this to `null` means the count is unrestricted.
  """
  maxCount: Int

  """
  Specify a custom type that implements the Edge interface
  to extend edge object.
  Only applies when using Relay style "connection" pagination.
  """
  edgeType: String
) on FIELD_DEFINITION

"""
Options for the `type` argument of `@morphToMany`.
"""
enum MorphToManyType {
  """
  Offset-based pagination, similar to the Laravel default.
  """
  PAGINATOR

  """
  Offset-based pagination like the Laravel "Simple Pagination", which does not count the total number of records.
  """
  SIMPLE

  """
  Cursor-based pagination, compatible with the Relay specification.
  """
  CONNECTION
}
```

## @namespace

```graphql
"""
Redefine the default namespaces used in other directives.
The arguments are a map from directive names to namespaces.
"""
directive @namespace repeatable on FIELD_DEFINITION | OBJECT
```

The following example applies the namespace `App\Blog`
to the [@field](#field) directive used on the `posts` field.

```graphql
type Query {
  posts: [Post!]!
    @field(resolver: "Post@resolveAll")
    @namespace(field: "App\\Blog")
}
```

When used upon an object type or an object type extension, the namespace
applies to fields of the type as well. This allows you to specify
a common namespace for a group of fields.

```graphql
extend type Query @namespace(field: "App\\Blog") {
  posts: [Post!]! @field(resolver: "Post@resolveAll")
}
```

A [@namespace](#namespace) directive defined on a field directive wins in case of a conflict.

## @namespaced

```graphql
"""
Provides a no-op field resolver that allows nesting of queries and mutations.
Useful to implement [namespacing by separation of concerns](https://www.apollographql.com/docs/technotes/TN0012-namespacing-by-separation-of-concern).
"""
directive @namespaced on FIELD_DEFINITION
```

The following example shows how one can namespace queries and mutations.

```graphql
type Query {
  post: PostQueries! @namespaced
}

type PostQueries {
  find(id: ID! @whereKey): Post @find
  list(title: String @where(operator: "like")): [Post!]! @paginate
}

type Mutation {
  post: PostMutations! @namespaced
}

type PostMutations {
  create(input: PostCreateInput! @spread): Post! @create
  update(input: PostUpdateInput! @spread): Post! @update
  delete(id: ID! @whereKey): Post! @delete
}
```

## @neq

```graphql
"""
Use the client given value to add a not-equal conditional to a database query.
"""
directive @neq(
  """
  Specify the database column to compare.
  Only required if database column has a different name than the attribute in your schema.
  """
  key: String
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

> This directive only works if the field resolver passes its builder through a call to `$resolveInfo->enhanceBuilder()`.
> Built-in field resolver directives that query the database do this, such as [@all](#all) or [@hasMany](#hasmany).

```graphql
type User {
  posts(excludeCategory: String @neq(key: "category")): [Post!]! @hasMany
}
```

## @nest

```graphql
"""
A no-op nested arg resolver that delegates all calls
to the ArgResolver directives attached to the children.
"""
directive @nest on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

This may be useful to logically group arg resolvers.

```graphql
type Mutation {
  createUser(name: String, tasks: UserTasksOperations @nest): User! @create
}

input UserTasksOperations {
  newTask: CreateTaskInput @create(relation: "tasks")
}

input CreateTaskInput {
  name: String
}

type Task {
  name: String!
}

type User {
  name: String
  tasks: [Task!]! @hasMany
}
```

## @node

```graphql
"""
Register a type for Relay's global object identification.

When used without any arguments, Lighthouse will attempt
to resolve the type through a model with the same name.
"""
directive @node(
  """
  Reference to a function that receives the decoded `id` and returns a result.
  Consists of two parts: a class name and a method name, seperated by an `@` symbol.
  If you pass only a class name, the method name defaults to `__invoke`.

  Mutually exclusive with `model`.
  """
  resolver: String

  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.

  Mutually exclusive with `resolver`.
  """
  model: String
) on OBJECT
```

When you use [@node](#node) on a type, Lighthouse will add a field `node` to the root Query type.
If you want to customize its description, change the resolver or add middleware, you can add it yourself like this:

```graphql
type Query {
  "This description is up to you."
  node(id: ID! @globalId): Node
    @field(resolver: "Nuwave\\Lighthouse\\GlobalId\\NodeRegistry@resolve")
    @someMiddlewareDirective
    @maybeAuthorization
}
```

Lighthouse defaults to resolving types through the underlying model,
for example by calling `User::find($id)`.

```graphql
type User @node {
  id: ID! @globalId
}
```

You can also use a custom resolver function to resolve any kind of data.

```graphql
type Country @node(resolver: "App\\Countries@byId") {
  name: String!
}
```

The `resolver` argument has to specify a function which will be passed the
decoded `id` and resolves to a result.

```php
public function byId($id): array
{
    return [
        'DE' => ['name' => 'Germany'],
        'MY' => ['name' => 'Malaysia'],
    ][$id];
}
```

[Read more](../digging-deeper/relay.md#global-object-identification).

Behind the scenes, Lighthouse will decode the global ID sent from the client
to find the model by its primary key in the database.

## @notIn

```graphql
"""
Use the client given value to add a NOT IN conditional to a database query.
"""
directive @notIn(
  """
  Specify the database column to compare.
  Only required if database column has a different name than the attribute in your schema.
  """
  key: String
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

> This directive only works if the field resolver passes its builder through a call to `$resolveInfo->enhanceBuilder()`.
> Built-in field resolver directives that query the database do this, such as [@all](#all) or [@hasMany](#hasmany).

```graphql
type Query {
  posts(excludeIds: [Int!] @notIn(key: "id")): [Post!]! @paginate
}
```

## @orderBy

```graphql
"""
Sort a result list by one or more given columns.
"""
directive @orderBy(
  """
  Restrict the allowed column names to a well-defined list.
  This improves introspection capabilities and security.
  Mutually exclusive with `columnsEnum`.
  Only used when the directive is added on an argument.
  """
  columns: [String!]

  """
  Use an existing enumeration type to restrict the allowed columns to a predefined list.
  This allows you to re-use the same enum for multiple fields.
  Mutually exclusive with `columns`.
  Only used when the directive is added on an argument.
  """
  columnsEnum: String

  """
  Allow clients to sort by aggregates on relations.
  Only used when the directive is added on an argument.
  """
  relations: [OrderByRelation!]

  """
  The database column for which the order by clause will be applied on.
  Only used when the directive is added on a field.
  """
  column: String

  """
  The direction of the order by clause.
  Only used when the directive is added on a field.
  """
  direction: OrderByDirection = ASC
) on ARGUMENT_DEFINITION | FIELD_DEFINITION

"""
Options for the `direction` argument of `@orderBy`.
"""
enum OrderByDirection {
  """
  Sort in ascending order.
  """
  ASC

  """
  Sort in descending order.
  """
  DESC
}

"""
Options for the `relations` argument of `@orderBy`.
"""
input OrderByRelation {
  """
  Name of the relation.
  """
  relation: String!

  """
  Restrict the allowed column names to a well-defined list.
  This improves introspection capabilities and security.
  Mutually exclusive with `columnsEnum`.
  """
  columns: [String!]

  """
  Use an existing enumeration type to restrict the allowed columns to a predefined list.
  This allows you to re-use the same enum for multiple fields.
  Mutually exclusive with `columns`.
  """
  columnsEnum: String
}
```

> This directive only works if the field resolver passes its builder through a call to `$resolveInfo->enhanceBuilder()`.
> Built-in field resolver directives that query the database do this, such as [@all](#all) or [@hasMany](#hasmany).

See [ordering](../digging-deeper/ordering.md).

## @paginate

```graphql
"""
Query multiple entries as a paginated list.
"""
directive @paginate(
  """
  Which pagination style should be used.
  """
  type: PaginateType = PAGINATOR

  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.
  Mutually exclusive with `builder` and `resolver`.
  """
  model: String

  """
  Point to a function that provides a Query Builder instance.
  Consists of two parts: a class name and a method name, seperated by an `@` symbol.
  If you pass only a class name, the method name defaults to `__invoke`.
  Mutually exclusive with `model` and `resolver`.
  """
  builder: String

  """
  Reference a function that resolves the field by directly returning data in a Paginator instance.
  Mutually exclusive with `builder` and `model`.
  Not compatible with `scopes` and builder arguments such as `@eq`.
  Consists of two parts: a class name and a method name, seperated by an `@` symbol.
  If you pass only a class name, the method name defaults to `__invoke`.
  """
  resolver: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]

  """
  Allow clients to query paginated lists without specifying the amount of items.
  Overrules the `pagination.default_count` setting from `lighthouse.php`.
  Setting this to `null` means clients have to explicitly ask for the count.
  """
  defaultCount: Int

  """
  Limit the maximum amount of items that clients can request from paginated lists.
  Overrules the `pagination.max_count` setting from `lighthouse.php`.
  Setting this to `null` means the count is unrestricted.
  """
  maxCount: Int

  """
  Reference a function to customize the complexity score calculation.
  Consists of two parts: a class name and a method name, seperated by an `@` symbol.
  If you pass only a class name, the method name defaults to `__invoke`.
  """
  complexityResolver: String
) on FIELD_DEFINITION

"""
Options for the `type` argument of `@paginate`.
"""
enum PaginateType {
  """
  Offset-based pagination, similar to the Laravel default.
  """
  PAGINATOR

  """
  Offset-based pagination like the Laravel "Simple Pagination", which does not count the total number of records.
  """
  SIMPLE

  """
  Cursor-based pagination, compatible with the Relay specification.
  """
  CONNECTION
}
```

### Basic usage

This directive is meant to be used on root query fields:

```graphql
type Query {
  posts: [Post!]! @paginate
}
```

> When you want to paginate a relationship, use the to-many relationship
> directives such as [@hasMany](directives.md#hasmany) instead.

The schema definition is automatically transformed to this:

```graphql
type Query {
  posts(
    "Limits number of fetched items."
    first: Int!

    "The offset from which items are returned."
    page: Int
  ): PostPaginator
}

"A paginated list of Post items."
type PostPaginator {
  "A list of Post items."
  data: [Post!]!

  "Pagination information about the list of items."
  paginatorInfo: PaginatorInfo!
}

"Information about pagination using a fully featured paginator."
type PaginatorInfo {
  "Number of items in the current page."
  count: Int!

  "Index of the current page."
  currentPage: Int!

  "Index of the first item in the current page."
  firstItem: Int

  "Are there more pages after this one?"
  hasMorePages: Boolean!

  "Index of the last item in the current page."
  lastItem: Int

  "Index of the last available page."
  lastPage: Int!

  "Number of items per page."
  perPage: Int!

  "Number of total available items."
  total: Int!
}
```

It can be queried like this:

```graphql
{
  posts(first: 10) {
    data {
      id
      title
    }
    paginatorInfo {
      currentPage
      lastPage
    }
  }
}
```

### Pagination type

The `type` of pagination defaults to `PAGINATOR`,
but may also be set to `SIMPLE` (see [Simple Pagination](#simple-pagination))
or a Relay compliant `CONNECTION`.

> Lighthouse does not support actual cursor-based pagination as of now, see https://github.com/nuwave/lighthouse/issues/311 for details.
> Under the hood, the "cursor" is decoded into a page offset.

```graphql
type Query {
  posts: [Post!]! @paginate(type: CONNECTION)
}
```

The final schema will be transformed to this:

```graphql
type Query {
  posts(
    "Limits number of fetched items."
    first: Int!

    "A cursor after which elements are returned."
    after: String
  ): PostConnection
}

"A paginated list of Post edges."
type PostConnection {
  "Pagination information about the list of edges."
  pageInfo: PageInfo!

  "A list of Post edges."
  edges: [PostEdge]
}

"An edge that contains a node of type Post and a cursor."
type PostEdge {
  "The Post node."
  node: Post

  "A unique cursor that can be used for pagination."
  cursor: String!
}
```

### Simple Pagination

In contrast to other pagination types, `SIMPLE` pagination only fires a single database
query on every request. This improves performance, but means that the response does not
hold information about the total number of items.

If you wish to use the `simplePaginate` method, set the `type` to `SIMPLE`.

> Please note that the `SIMPLE` paginator does not have the attributes
> `lastPage` and `total`.
>
> If you need those fields, you should use the default `PAGINATOR`.

```graphql
type Query {
  posts: [Post!]! @paginate(type: SIMPLE)
}
```

The schema definition is automatically transformed to this:

```graphql
type Query {
  posts(
    "Limits number of fetched items."
    first: Int!

    "The offset from which items are returned."
    page: Int
  ): PostSimplePaginator
}

"A paginated list of Post items."
type PostSimplePaginator {
  "A list of Post items."
  data: [Post!]!

  "Pagination information about the list of items."
  paginatorInfo: SimplePaginatorInfo!
}

"Information about pagination using a simple paginator."
type SimplePaginatorInfo {
  "Number of items in the current page."
  count: Int!

  "Index of the current page."
  currentPage: Int!

  "Index of the first item in the current page."
  firstItem: Int

  "Index of the last item in the current page."
  lastItem: Int

  "Number of items per page."
  perPage: Int!

  "Are there more pages after this one?"
  hasMorePages: Boolean!
}
```

It can be queried like this:

```graphql
{
  posts(first: 10) {
    data {
      id
      title
    }
    paginatorInfo {
      currentPage
    }
  }
}
```

### Default count

You can supply a `defaultCount` to set a default count for any type of pagination.

```graphql
type Query {
  posts: [Post!]! @paginate(type: CONNECTION, defaultCount: 25)
}
```

This lets you omit the `count` argument when querying:

```graphql
query {
  posts {
    id
    name
  }
}
```

### Limit maximum count

Lighthouse allows you to specify a global maximum for the number of items a user
can request through pagination through the config. You may also overwrite this
per field with the `maxCount` argument:

```graphql
type Query {
  posts: [Post!]! @paginate(maxCount: 10)
}
```

### Overwrite model

By default, Lighthouse looks for an Eloquent model in the configured default namespace, with the same
name as the returned type. You can overwrite this by setting the `model` argument.

```graphql
type Query {
  posts: [Post!]! @paginate(model: "App\\Blog\\BlogPost")
}
```

### Custom builder

If simply querying Eloquent does not fit your use-case, you can specify a custom `builder`.

```graphql
type Query {
  blogStatistics: [BlogStatistic!]! @paginate(builder: "App\\Blog@statistics")
}
```

Your method receives the typical resolver arguments and has to return an instance of `Illuminate\Database\Query\Builder`.

> If you actually want to query a model and possibly its relations through nested fields,
> make sure to return an Eloquent builder, e.g. `Post::query()`.

```php
namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final class Blog
{
    public function statistics(mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Builder
    {
        return DB::table('posts')
            ->leftJoinSub(...)
            ->groupBy(...);
    }
}
```

### Custom resolver

You can provide your own function that resolves the field by directly returning data in a `\Illuminate\Contracts\Pagination\Paginator` instance.

This is mutually exclusive with `builder` and `model`. Not compatible with `scopes` and builder arguments such as [@eq](#eq).

```graphql
type Query {
  posts: [Post!]! @paginate(resolver: "App\\GraphQL\\Queries\\Posts")
}
```

A custom resolver function may look like the following:

```php
namespace App\GraphQL\Queries;

use Illuminate\Pagination\LengthAwarePaginator;

final class Posts
{
    /**
     * @param  null  $root Always null, since this field has no parent.
     * @param  array{}  $args The field arguments passed by the client.
     * @param  \Nuwave\Lighthouse\Support\Contracts\GraphQLContext  $context Shared between all fields.
     * @param  \GraphQL\Type\Definition\ResolveInfo  $resolveInfo Metadata for advanced query resolution.
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): LengthAwarePaginator
    {
        //...apply your logic
        return new LengthAwarePaginator([
            [
                'id' => 1,
                'title' => 'Flying teacup found in solar orbit',
            ],
            [
                'id' => 2,
                'title' => 'What actually is the difference between cookies and biscuits?',
            ],
        ], 2, 15);
    }
}
```

## @rename

```graphql
"""
Change the internally used name of a field or argument.

This does not change the schema from a client perspective.
"""
directive @rename(
  """
  The internal name of an attribute/property/key.
  """
  attribute: String!
) on FIELD_DEFINITION | ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

This can often be useful to ensure consistent naming of your schema
without having to change the underlying models.

```graphql
type User {
  createdAt: String! @rename(attribute: "created_at")
}

input UserInput {
  firstName: String! @rename(attribute: "first_name")
}
```

## @restore

```graphql
"""
Un-delete one or more soft deleted models.
"""
directive @restore(
  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.
  """
  model: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) on FIELD_DEFINITION
```

Use it on a root mutation field that returns an instance of the Model.

```graphql
type Mutation {
  restorePost(id: ID! @whereKey): Post @restore
}
```

Works very similar to the [@delete](#delete) directive.

## @rules

```graphql
"""
Validate an argument using [Laravel validation](https://laravel.com/docs/validation).
"""
directive @rules(
  """
  Specify the validation rules to apply to the field.
  This can either be a reference to [Laravel's built-in validation rules](https://laravel.com/docs/validation#available-validation-rules),
  or the fully qualified class name of a custom validation rule.

  Validation rules that mutate the given input values are _not_ supported:
  - `exclude_if`
  - `exclude_unless`
  Use ArgTransformerDirectives or FieldMiddlewareDirectives instead.
  """
  apply: [String!]!

  """
  Specify a custom attribute name to use in your validation message.
  """
  attribute: String

  """
  Specify the messages to return if the validators fail.
  """
  messages: [RulesMessage!]
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION

"""
Input for the `messages` argument of `@rules`.
"""
input RulesMessage {
  """
  Name of the rule, e.g. `"email"`.
  """
  rule: String!

  """
  Message to display if the rule fails, e.g. `"Must be a valid email"`.
  """
  message: String!
}
```

For example, this rule ensures that users pass a valid 2 character country code:

```graphql
type Query {
  users(countryCode: String @rules(apply: ["string", "size:2"])): [User!]! @all
}
```

Read more in the [validation docs](../security/validation.md#single-arguments).

## @rulesForArray

```graphql
"""
Run validation on an array itself, using [Laravel built-in validation](https://laravel.com/docs/validation).
"""
directive @rulesForArray(
  """
  Specify the validation rules to apply to the field.
  This can either be a reference to any of Laravel's built-in validation rules: https://laravel.com/docs/validation#available-validation-rules,
  or the fully qualified class name of a custom validation rule.

  Validation rules that mutate the given input values are _not_ supported:
  - `exclude_if`
  - `exclude_unless`
  Use ArgTransformerDirectives or FieldMiddlewareDirectives instead.
  """
  apply: [String!]!

  """
  Specify a custom attribute name to use in your validation message.
  """
  attribute: String

  """
  Specify the messages to return if the validators fail.
  """
  messages: [RulesForArrayMessage!]
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION

"""
Input for the `messages` argument of `@rulesForArray`.
"""
input RulesForArrayMessage {
  """
  Name of the rule, e.g. `"email"`.
  """
  rule: String!

  """
  Message to display if the rule fails, e.g. `"Must be a valid email"`.
  """
  message: String!
}
```

This is typically used to assert a certain number of elements is given in a list.

```graphql
type Mutation {
  saveIcecream(
    flavors: [IcecreamFlavor!]! @rulesForArray(apply: ["min:3"])
  ): Icecream
}
```

Read more in the [validation docs](../security/validation.md#validating-arrays).

## @scalar

```graphql
"""
Reference a class implementing a scalar definition.
"""
directive @scalar(
  """
  Reference to a class that extends `\GraphQL\Type\Definition\ScalarType`.
  """
  class: String!
) on SCALAR
```

If you follow the namespace convention, you do not need this directive.
Lighthouse looks into your configured scalar namespace for a class with the same name.

[Learn how to implement your own scalar.](https://webonyx.github.io/graphql-php/type-definitions/scalars)

```graphql
scalar DateTime @scalar(class: "DateTimeScalar")
```

If your class is not in the default namespace, pass a fully qualified class name.

```graphql
scalar DateTime
  @scalar(class: "Nuwave\\Lighthouse\\Schema\\Types\\Scalars\\DateTime")
```

## @scope

```graphql
"""
Adds a scope to the query builder.

The scope method will receive the client-given value of the argument as the second parameter.
This also works with custom query builders, it simply calls its methods with the argument value.
"""
directive @scope(
  """
  The name of the scope or method on the custom query builder.
  Defaults to the name of the argument or input field.
  """
  name: String
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

> This directive only works if the field resolver passes its builder through a call to `$resolveInfo->enhanceBuilder()`.
> Built-in field resolver directives that query the database do this, such as [@all](#all) or [@hasMany](#hasmany).

```graphql
type Query {
  posts(trending: Boolean @scope): [Post!]! @all
}
```

The scope will be passed the value of the client-given argument:

```php
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class Post extends Model
{
    public function scopeTrending(Builder $query, bool $trending): Builder { ... }
}
```

You can use the `name` argument if your scope is named differently from your argument:

```graphql
type Query {
  posts(isTrending: Boolean @scope(name: "trending")): [Post!] @all
}
```

## @search

```graphql
"""
Perform a full-text search by the given input value.
"""
directive @search(
  """
  Specify a custom index to use for search.
  """
  within: String
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

Requires the installation of [Laravel Scout](https://laravel.com/docs/scout)
and manual registration of the service provider in `config/app.php`:

```php
'providers' => [
    \Nuwave\Lighthouse\Scout\ScoutServiceProvider::class,
],
```

The `search()` method of the model is called with the value of the argument,
using the driver you configured for Scout.

```graphql
type Query {
  posts(search: String @search): [Post!]! @paginate
}
```

The [@search](#search) directive only works in combination with filter directives that
implement the interface `Nuwave\Lighthouse\Scout\ScoutBuilderDirective`:

- [@eq](#eq)
- [@softDeletes](#softdeletes)

Normally the search will be performed using the index specified by the model's `searchableAs` method.
However, in some situation a custom index might be needed, this can be achieved by using the argument `within`.

```graphql
type Query {
  posts(search: String @search(within: "my.index")): [Post!]! @paginate
}
```

## @show

```graphql
"""
Includes the annotated element from the schema conditionally.
"""
directive @show(
  """
  Specify which environments include this element, e.g. ["testing"].
  Compared against the value returned from `app()->environment()`.
  """
  env: [String!]!
) repeatable on FIELD_DEFINITION
```

See [feature toggles](../digging-deeper/feature-toggles.md).

## @softDeletes

```graphql
"""
Allows to filter if trashed elements should be fetched.
This manipulates the schema by adding the argument
`trashed: Trashed @trashed` to the field.
"""
directive @softDeletes on FIELD_DEFINITION
```

The following schema definition from a `.graphql` file:

```graphql
type Query {
  tasks: [Tasks!]! @all @softDeletes
}
```

Will result in a schema that looks like this:

```graphql
type Query {
  tasks(trashed: Trashed @trashed): [Tasks!]! @all
}
```

Find out how the added filter works: [@trashed](#trashed)

## @spread

```graphql
"""
Merge the fields of a nested input object into the arguments of its parent
when processing the field arguments given by a client.
"""
directive @spread on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

You may use [@spread](#spread) on field arguments or on input object fields:

```graphql
type Mutation {
  updatePost(id: ID!, input: PostInput! @spread): Post! @update
}

input PostInput {
  title: String!
  content: PostContent @spread
}

input PostContent {
  imageUrl: String
}
```

The schema does not change, client side usage works as if [@spread](#spread) was not there:

```graphql
mutation {
  updatePost(
    id: 12
    input: {
      title: "My awesome title"
      content: { imageUrl: "https://some.site/image.jpg" }
    }
  ) {
    id
  }
}
```

Internally, the arguments will be transformed into a flat structure before
they are passed along to the resolver:

```php
[
    'id' => 12,
    'title' => 'My awesome title',
    'imageUrl' = 'https://some.site/image.jpg',
]
```

Note that Lighthouse spreads out the arguments **after** all other [ArgDirectives](../custom-directives/field-argument-directives)
have been applied, e.g. validation, transformation.

## @subscription

```graphql
"""
Reference a class to handle the broadcasting of a subscription to clients.
The given class must extend `\Nuwave\Lighthouse\Schema\Types\GraphQLSubscription`.
"""
directive @subscription(
  """
  A reference to a subclass of `\Nuwave\Lighthouse\Schema\Types\GraphQLSubscription`.
  """
  class: String!
) on FIELD_DEFINITION
```

If you follow the default naming conventions for [defining subscription fields](../subscriptions/defining-fields.md)
you do not need this directive. It is only useful if you need to override the default namespace.

```graphql
type Subscription {
  postUpdated(author: ID!): Post
    @subscription(class: "App\\GraphQL\\Blog\\PostUpdatedSubscription")
}
```

## @throttle

```graphql
"""
Sets rate limit to access the field. Does the same as ThrottleRequests Laravel Middleware.
"""
directive @throttle(
  """
  Named preconfigured rate limiter.
  """
  name: String

  """
  Maximum number of attempts in a specified time interval.
  """
  maxAttempts: Int = 60

  """
  Time in minutes to reset attempts.
  """
  decayMinutes: Float = 1.0

  """
  Prefix to distinguish several field groups.
  """
  prefix: String
) on FIELD_DEFINITION
```

Allows use Laravel throttling on a per-field basis. See [Laravel doc](https://laravel.com/docs/routing#rate-limiting)
on how to configure named limiters.

Limiters that return `response` are not supported. Hashes are different from the ones of Laravel, so one can't use
one named limiter to limit both Laravel route and GraphQL field.

## @trashed

```graphql
"""
Allows to filter if trashed elements should be fetched.
"""
directive @trashed on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

> This directive only works if the field resolver passes its builder through a call to `$resolveInfo->enhanceBuilder()`.
> Built-in field resolver directives that query the database do this, such as [@all](#all) or [@hasMany](#hasmany).

The most convenient way to use this directive is through [@softDeletes](#softdeletes).

If you want to add it manually, make sure the argument is of the
enum type `Trashed`:

```graphql
type Query {
  flights(trashed: Trashed @trashed): [Flight!]! @all
}
```

## @trim

```graphql
"""
Remove whitespace from the beginning and end of a given input.

This can be used on:
- a single argument or input field to sanitize that subtree
- a field to trim all strings
"""
directive @trim on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION | FIELD_DEFINITION
```

Whitespace around the passed in string will be removed.

```graphql
type Mutation {
  createUser(name: String @trim): User
}
```

Usage on a field applies `trim` recursively to all inputs.

```graphql
type Mutation {
  createUser(input: CreateUserInput): User @trim
}
```

If you want this for all your fields, consider adding this directive to your
global field middleware in `lighthouse.php`:

```php
    'field_middleware' => [
        \Nuwave\Lighthouse\Schema\Directives\TrimDirective::class,
        ...
    ],
```

## @union

```graphql
"""
Use a custom function to determine the concrete type of unions.
"""
directive @union(
  """
  Reference a function that returns the implementing Object Type.
  Consists of two parts: a class name and a method name, seperated by an `@` symbol.
  If you pass only a class name, the method name defaults to `__invoke`.
  """
  resolveType: String!
) on UNION
```

Make sure you read the [basics about Unions](../the-basics/types.md#union) before deciding
to use this directive, you probably don't need it.

```graphql
type User {
  id: ID!
}

type Employee {
  employeeId: ID!
}

union Person @union(resolveType: "App\\GraphQL\\Unions\\Person@resolveType") =
    User
  | Employee
```

The function receives the value of the parent field as its single argument and must
resolve an Object Type from Lighthouse's `TypeRegistry`.

```php
namespace App\GraphQL\Unions;

use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final class Person
{
    public function __construct(
        private TypeRegistry $typeRegistry
    ) {}

    /**
     * Decide which GraphQL type a resolved value has.
     *
     * @param  mixed  $root The value that was resolved by the field. Usually an Eloquent model.
     */
    public function resolveType(mixed $root, GraphQLContext $context, ResolveInfo $resolveInfo): Type
    {
        // TODO implement your own resolver logic - if the default is fine, just delete this class
    }
}
```

## @update

```graphql
"""
Update an Eloquent model with the given arguments.
"""
directive @update(
  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.
  """
  model: String

  """
  Specify the name of the relation on the parent model.
  This is only needed when using this directive as a nested arg
  resolver and if the name of the relation is not the arg name.
  """
  relation: String
) on FIELD_DEFINITION | ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

Use it on a root mutation field that returns an instance of the Model.

```graphql
type Mutation {
  updatePost(id: ID!, content: String): Post! @update
}
```

If the primary key of your model is not called `id`, it is recommended to rename it.
Client libraries such as Apollo base their caching mechanism on that assumption.

```graphql
type Mutation {
  updatePost(id: ID! @rename(attribute: "post_id"), content: String): Post!
    @update
}
```

If the name of the Eloquent model does not match the return type of the field,
or is located in a non-default namespace, set it with the `model` argument.

```graphql
type Mutation {
  updateAuthor(id: ID!, name: String): Author! @update(model: "App\\User")
}
```

This directive can also be used as a [nested arg resolver](../concepts/arg-resolvers.md).

## @updateMany

```graphql
"""
Update multiple Eloquent models with the given arguments.
"""
directive @updateMany(
  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.
  """
  model: String

  """
  Specify the name of the relation on the parent model.
  This is only needed when using this directive as a nested arg
  resolver and if the name of the relation is not the arg name.
  """
  relation: String
) on FIELD_DEFINITION | ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

When used on a field, it must have exactly one argument where the type is a non-null list of input objects.

```graphql
type Mutation {
  updatePosts(inputs: [UpdatePostInput!]!): [Post!]! @updateMany
}

input UpdatePostInput {
  id: ID!
  title: String
}
```

## @upload

```graphql
"""
Uploads given file to storage, removes the argument and sets
the returned path to the attribute key provided.

This does not change the schema from a client perspective.
"""
directive @upload(
  """
  The storage disk to be used, defaults to config value `filesystems.default`.
  """
  disk: String

  """
  The path where the file should be stored.
  """
  path: String! = "/"

  """
  Should the visibility be public?
  """
  public: Boolean! = false
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

This is useful when you want pass in a file as an argument but have it upload and resolve to a filepath.
For example, you want to pass in a user avatar, have that file uploaded and the resulting filepath stored as a row in a database table.

```graphql
type Mutation {
  createUser(
    avatar: Upload @upload(disk: "public", path: "images/avatars", public: true)
  ): User! @create
}

type User {
  avatar: String!
}
```

## @upsert

```graphql
"""
Create or update an Eloquent model with the given arguments.
"""
directive @upsert(
  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.
  """
  model: String

  """
  Specify the name of the relation on the parent model.
  This is only needed when using this directive as a nested arg
  resolver and if the name of the relation is not the arg name.
  """
  relation: String
) on FIELD_DEFINITION | ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

Lighthouse will try to fetch the model by its primary key, just like [@update](#update).
If the model doesn't exist, it will be newly created with a given `id`.
In case no `id` is specified, an auto-generated fresh ID will be used instead.

```graphql
type Mutation {
  upsertPost(id: ID, content: String!): Post! @upsert
}
```

This directive can also be used as a [nested arg resolver](../concepts/arg-resolvers.md).

## @upsertMany

```graphql
"""
Create or update multiple Eloquent models with given arguments.
"""
directive @upsertMany(
  """
  Specify the class name of the model to use.
  This is only needed when the default model detection does not work.
  """
  model: String

  """
  Specify the name of the relation on the parent model.
  This is only needed when using this directive as a nested arg
  resolver and if the name of the relation is not the arg name.
  """
  relation: String
) on FIELD_DEFINITION | ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

When used on a field, it must have exactly one argument where the type is a non-null list of input objects.

```graphql
type Mutation {
  upsertPosts(inputs: [UpsertPostInput!]!): [Post!]! @upsertMany
}

input UpsertPostInput {
  id: ID
  title: String!
}
```

## @validator

```graphql
"""
Provide validation rules through a PHP class.
"""
directive @validator(
  """
  The name of the class to use.

  If defined on an input, this defaults to a class called `{$inputName}Validator` in the
  default validator namespace. For fields, it uses the namespace of the parent type
  and the field name: `{$parent}\{$field}Validator`.
  """
  class: String
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION | FIELD_DEFINITION | INPUT_OBJECT
```

Read more in the [validation docs](../security/validation.md#validator-classes).

## @where

```graphql
"""
Use an input value as a [where filter](https://laravel.com/docs/queries#where-clauses).
"""
directive @where(
  """
  Specify the operator to use within the WHERE condition.
  """
  operator: String = "="

  """
  Specify the database column to compare.
  Only required if database column has a different name than the attribute in your schema.
  """
  key: String

  """
  Use Laravel's where clauses upon the query builder.
  This only works for clauses with the signature (string $column, string $operator, mixed $value).
  """
  clause: String

  """
  Provide a value to compare against.
  Exclusively required when this directive is used on a field.
  """
  value: WhereValue

  """
  Treat explicit `null` as if the argument is not present in the request?
  """
  ignoreNull: Boolean! = false
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION | FIELD_DEFINITION

"""
Any constant literal value: https://graphql.github.io/graphql-spec/draft/#sec-Input-Values
"""
scalar WhereValue
```

> This directive only works if the field resolver passes its builder through a call to `$resolveInfo->enhanceBuilder()`.
> Built-in field resolver directives that query the database do this, such as [@all](#all) or [@hasMany](#hasmany).

You can specify simple operators:

```graphql
type Query {
  postsSearchTitle(title: String! @where(operator: "like")): [Post!]! @all
}
```

Or use the additional clauses that Laravel provides:

```graphql
type Query {
  postsByYear(created_at: Int! @where(clause: "whereYear")): [Post!]! @all
}
```

When used on a field, you must define `key` and `value`:

```graphql
type Query {
  importantPosts: [Post!]! @all @where(key: "priority", operator: ">", value: 5)
}
```

If you want to prevent explicit `null` values to be passed to the query you can use `ignoreNull`:

```graphql
type Post {
    id: ID!
    # Never null
    title: String!
}

type Query {
    posts(title: String @where(ignoreNull: true)): [Post!]! @all
}

query {
    posts {
        # gets all posts
    }

    posts(title: null) {
        # same result as above
    }
}
```

## @whereAuth

```graphql
"""
Filter a type to only return instances owned by the current user.
"""
directive @whereAuth(
  """
  Name of the relationship that links to the user model.
  """
  relation: String!

  """
  Specify which guards to use, e.g. ["api"].
  When not defined, the default from `lighthouse.php` is used.
  """
  guards: [String!]
) on FIELD_DEFINITION
```

> This directive only works if the field resolver passes its builder through a call to `$resolveInfo->enhanceBuilder()`.
> Built-in field resolver directives that query the database do this, such as [@all](#all) or [@hasMany](#hasmany).

The following query returns all posts that belong to the currently authenticated user.  
Behind the scenes it is using a `whereHas` query.

```graphql
type Query {
  posts: [Post!]! @all @whereAuth(relation: "user")
}
```

## @whereBetween

```graphql
"""
Verify that a column's value is between two values.

The type of the input value this is defined upon should be
an `input` object with two fields.
"""
directive @whereBetween(
  """
  Specify the database column to compare.
  Only required if database column has a different name than the attribute in your schema.
  """
  key: String
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

> This directive only works if the field resolver passes its builder through a call to `$resolveInfo->enhanceBuilder()`.
> Built-in field resolver directives that query the database do this, such as [@all](#all) or [@hasMany](#hasmany).

This example defines an `input` to filter that a value is between two dates.

```graphql
type Query {
  posts(created_at: DateRange @whereBetween): [Post!]! @all
}

input DateRange {
  from: Date!
  to: Date!
}
```

You may use any custom `input` type for the argument. Make sure it has
exactly two required fields to ensure the query is valid.

## @whereConditions

The documentation for this directive is found in [`Complex Where Conditions`](../eloquent/complex-where-conditions.md#whereconditions).

## @whereHasConditions

The documentation for this directive is found in [`Complex Where Conditions`](../eloquent/complex-where-conditions.md#wherehasconditions).

## @whereJsonContains

```graphql
"""
Use an input value as a [whereJsonContains filter](https://laravel.com/docs/queries#json-where-clauses).
"""
directive @whereJsonContains(
  """
  Specify the database column and path inside the JSON to compare.
  Only required if database column has a different name than the attribute in your schema.
  """
  key: String
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

> This directive only works if the field resolver passes its builder through a call to `$resolveInfo->enhanceBuilder()`.
> Built-in field resolver directives that query the database do this, such as [@all](#all) or [@hasMany](#hasmany).

```graphql
type Query {
  posts(tags: [String]! @whereJsonContains): [Post!]! @all
}
```

You may use the `key` argument to look into the JSON content:

```graphql
type Query {
  posts(tags: [String]! @whereJsonContains(key: "tags->recent")): [Post!]! @all
}
```

## @whereKey

```graphql
"""
Add a where clause on the primary key to the Eloquent Model query.
"""
directive @whereKey(
  """
  Provide a value to compare against.
  Exclusively required when this directive is used on a field.
  """
  value: WhereKeyValue
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION | FIELD_DEFINITION

"""
Any constant literal value: https://graphql.github.io/graphql-spec/draft/#sec-Input-Values
"""
scalar WhereKeyValue
```

Use together with directives that make Eloquent queries such as [@find](#find), [@all](#all) or [@delete](#delete).

```graphql
type Query {
  post(id: ID! @whereKey): Post @find
  posts(ids: [ID!]! @whereKey): [Post!]! @all
}

type Mutation {
  deletePost(id: ID! @whereKey): Post! @delete
}
```

## @whereNotBetween

```graphql
"""
Verify that a column's value lies outside two values.

The type of the input value this is defined upon should be
an `input` object with two fields.
"""
directive @whereNotBetween(
  """
  Specify the database column to compare.
  Only required if database column has a different name than the attribute in your schema.
  """
  key: String
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

> This directive only works if the field resolver passes its builder through a call to `$resolveInfo->enhanceBuilder()`.
> Built-in field resolver directives that query the database do this, such as [@all](#all) or [@hasMany](#hasmany).

```graphql
type Query {
  posts(
    notCreatedDuring: DateRange @whereNotBetween(key: "created_at")
  ): [Post!]! @all
}

input DateRange {
  from: Date!
  to: Date!
}
```

## @whereNotNull

```graphql
"""
Filter the value is not null.
"""
directive @whereNotNull(
  """
  Specify the database column to compare.
  Only required if database column has a different name than the attribute in your schema.
  """
  key: String

  """
  Should the value not be null?
  Exclusively required when this directive is used on a field.
  """
  value: Boolean
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION | FIELD_DEFINITION
```

> This directive only works if the field resolver passes its builder through a call to `$resolveInfo->enhanceBuilder()`.
> Built-in field resolver directives that query the database do this, such as [@all](#all) or [@hasMany](#hasmany).

```graphql
type Query {
  posts(withTitle: Boolean @whereNotNull(key: "title")): [Post!]! @all
}
```

## @whereNull

```graphql
"""
Filter the value is null.
"""
directive @whereNull(
  """
  Specify the database column to compare.
  Only required if database column has a different name than the attribute in your schema.
  """
  key: String

  """
  Should the value be null?
  Exclusively required when this directive is used on a field.
  """
  value: Boolean
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION | FIELD_DEFINITION
```

> This directive only works if the field resolver passes its builder through a call to `$resolveInfo->enhanceBuilder()`.
> Built-in field resolver directives that query the database do this, such as [@all](#all) or [@hasMany](#hasmany).

```graphql
type Query {
  posts(unpublished: Boolean @whereNull(key: "published_at")): [Post!]! @all
}
```

## @with

```graphql
"""
Eager-load an Eloquent relation.
"""
directive @with(
  """
  Specify the relationship method name in the model class,
  if it is named different from the field in the schema.
  """
  relation: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) repeatable on FIELD_DEFINITION
```

This can be a useful optimization for fields that are not returned directly
but rather used for resolving other fields.

```graphql
type User {
  taskSummary: String! @with(relation: "tasks") @method(name: "getTaskSummary")
}
```

If you just want to return the relation itself as-is,
look into [handling Eloquent relationships](../eloquent/relationships.md).

## @withCount

```graphql
"""
Eager-load the count of an Eloquent relation if the field is queried.

Note that this does not return a value for the field, the count is simply
prefetched, assuming it is used to compute the field value. Use `@count`
if the field should simply return the relation count.
"""
directive @withCount(
  """
  Specify the relationship method name in the model class.
  """
  relation: String!

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) repeatable on FIELD_DEFINITION
```

This can be a useful optimization for fields that use the count to compute a result.

```graphql
type User {
  activityStatistics: ActivityStatistics! @withCount(relation: "posts")
}
```

If you just want to return the count itself as-is, use [@count](#count).

## @withoutGlobalScopes

```graphql
"""
Omit any number of global scopes from the query builder.

This directive should be used on arguments of type `Boolean`.
The scopes will be removed only if `true` is passed by the client.
"""
directive @withoutGlobalScopes(
  """
  The names of the global scopes to omit.
  """
  names: [String!]!
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

> This directive only works if the field resolver passes its builder through a call to `$resolveInfo->enhanceBuilder()`.
> Built-in field resolver directives that query the database do this, such as [@all](#all) or [@hasMany](#hasmany).

```php
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class Post extends Model
{
    protected static function booted(): void
    {
        self::addGlobalScope('scheduled', fn (Builder $query): Builder => $query
            ->whereNotNull('schedule_at'));
    }
}
```

```graphql
type Query {
  posts(
    includeUnscheduled: Boolean @withoutGlobalScopes(names: ["scheduled"])
  ): [Post!]! @all
}
```
