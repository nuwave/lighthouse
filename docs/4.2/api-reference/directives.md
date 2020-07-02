# Directives

## @all

Fetch all Eloquent models and return the collection as the result for a field.

```graphql
type Query {
  users: [User!]! @all
}
```

This assumes your model has the same name as the type you are returning and is defined
in the default model namespace `App`. [You can change this configuration](../getting-started/configuration.md).

### Definition

```graphql
directive @all(
  """
  Specify the class name of the model to use.
  This is only needed when the default model resolution does not work.
  """
  model: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) on FIELD_DEFINITION
```

### Examples

If you need to use a different model for a single field, you can pass a class name as the `model` argument.

```graphql
type Query {
  posts: [Post!]! @all(model: "App\\Blog\\BlogEntry")
}
```

## @auth

Return the currently authenticated user as the result of a query.

```graphql
type Query {
  me: User @auth
}
```

### Definition

```graphql
"""
Return the currently authenticated user as the result of a query.
"""
directive @auth(
  """
  Use a particular guard to retrieve the user.
  """
  guard: String
) on FIELD_DEFINITION
```

### Examples

If you need to use a guard besides the default to resolve the authenticated user,
you can pass the guard name as the `guard` argument

```graphql
type Query {
  me: User @auth(guard: "api")
}
```

## @belongsTo

Resolves a field through the Eloquent `BelongsTo` relationship.

```graphql
type Post {
  author: User @belongsTo
}
```

It assumes both the field and the relationship method to have the same name.

```php
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

### Definition

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

### Examples

The directive accepts an optional `relation` argument if your relationship method
has a different name than the field.

```graphql
type Post {
  user: User @belongsTo(relation: "author")
}
```

## @belongsToMany

Resolves a field through the Eloquent `BelongsToMany` relationship.

```graphql
type User {
  roles: [Role!]! @belongsToMany
}
```

It assumes both the field and the relationship method to have the same name.

```php
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Model
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
```

### Definition

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
  ALlows to resolve the relation as a paginated list.
  Allowed values: paginator, connection.
  """
  type: String

  """
  Specify the default quantity of elements to be returned.
  Only applies when using pagination.
  """
  defaultCount: Int

  """
  Specify the maximum quantity of elements to be returned.
  Only applies when using pagination.
  """
  maxCount: Int

  """
  Specify a custom type that implements the Edge interface
  to extend edge object.
  Only applies when using Relay style "connection" pagination.
  """
  edgeType: String
) on FIELD_DEFINITION
```

### Examples

The directive accepts an optional `relation` argument if your relationship method
has a different name than the field.

```graphql
type User {
  jobs: [Role!]! @belongsToMany(relation: "roles")
}
```

When using the connection `type` argument, you may create your own
[Edge type](https://facebook.github.io/relay/graphql/connections.htm#sec-Edge-Types) which
may have fields that resolve from the model [pivot](https://laravel.com/docs/5.8/eloquent-relationships#many-to-many)
data. You may also add a custom field resolver for fields you want to resolve yourself.

You may either specify the edge using the `edgetype` argument, or it will automatically
look for a {type}Edge type to be defined. In this case it would be `RoleEdge`.

```graphql
type User {
  roles: [Role!]! @belongsToMany(type: "connection", edgeType: "CustomRoleEdge")
}

type CustomRoleEdge implements Edge {
  cursor: String!
  node: Node
  meta: String
}
```

## @bcrypt

Run the `bcrypt` function on the argument it is defined on.

```graphql
type Mutation {
  createUser(name: String, password: String @bcrypt): User
}
```

### Definition

```graphql
"""
Run the `bcrypt` function on the argument it is defined on.
"""
directive @bcrypt on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

## @broadcast

Broadcast the results of a mutation to subscribed clients.
[Read more about subscriptions](../subscriptions/getting-started.md)

```graphql
type Mutation {
  createPost(input: CreatePostInput!): Post
    @broadcast(subscription: "postCreated")
}
```

The `subscription` argument must reference the name of a subscription field.

### Definition

```graphql
"""
Broadcast the results of a mutation to subscribed clients.
"""
directive @broadcast(
  """
  Name of the subscription that should be retriggered as a result of this operation..
  """
  subscription: String!

  """
  Specify whether or not the job should be queued.
  This defaults to the global config option `lighthouse.subscriptions.queue_broadcasts`.
  """
  shouldQueue: Boolean
) on FIELD_DEFINITION
```

### Examples

You may override the default queueing behaviour from the configuration by
passing the `shouldQueue` argument.

```graphql
type Mutation {
  updatePost(input: UpdatePostInput!): Post
    @broadcast(subscription: "postUpdated", shouldQueue: false)
}
```

## @builder

Use an argument to modify the query builder for a field.

```graphql
type Query {
    users(
        limit: Int @builder(method: "App\MyClass@limit")
    ): [User!]! @all
}
```

You must point to a `method` which will receive the builder instance
and the argument value and can apply additional constraints to the query.

```php
namespace App;

class MyClass
{

     * Add a limit constrained upon the query.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $builder
     * @param  mixed  $value
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function limit($builder, int $value)
    {
        return $builder->limit($value);
    }
}
```

### Definition

```graphql
"""
Use an argument to modify the query builder for a field.
"""
directive @builder(
  """
  Reference a method that is passed the query builder.
  Consists of two parts: a class name and a method name, separated by an `@` symbol.
  If you pass only a class name, the method name defaults to `__invoke`.
  """
  method: String!
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

## @cache

Cache the result of a resolver.

The cache is created on the first request and is cached forever by default.
Use this for values that change seldom and take long to fetch/compute.

```graphql
type Query {
  highestKnownPrimeNumber: Int! @cache
}
```

### Definition

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

### Examples

You can set an expiration time in seconds
if you want to invalidate the cache after a while.

```graphql
type Query {
  temperature: Int! @cache(maxAge: 300)
}
```

You can limit the cache to the logged in user making the request by marking it as private.
This makes sense for data that is specific to a certain user.

```graphql
type Query {
  todos: [ToDo!]! @cache(private: true)
}
```

## @cacheKey

Specify the field to use as a key when creating a cache.

```graphql
type GithubProfile {
  username: String @cacheKey
  repos: [Repository] @cache
}
```

When generating a cached result for a resolver, Lighthouse produces a unique key for each type.
By default, Lighthouse will look for a field with the `ID` type to generate the key.
If you'd like to use a different field (i.e., an external API id) you can mark the field with the `@cacheKey` directive.

### Definition

```graphql
"""
Specify the field to use as a key when creating a cache.
"""
directive @cacheKey on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

## @can

Check a Laravel Policy to ensure the current user is authorized to access a field.

```graphql
type Mutation {
  createPost(input: PostInput): Post @can(ability: "create")
}
```

```php
class PostPolicy
{
    public function create(User $user): bool
    {
        return $user->is_admin;
    }
}
```

### Definition

```graphql
"""
Check a Laravel Policy to ensure the current user is authorized to access a field.
"""
directive @can(
  """
  The ability to check permissions for.
  """
  ability: String!

  """
  If your policy checks against specific model instances, specify
  the name of the field argument that contains its primary key(s).
  """
  find: String

  """
  Additional arguments that are passed to `Gate::check`.
  """
  args: [String!]
) on FIELD_DEFINITION
```

### Examples

You may specify an argument that is used to find a specific model
instance against which the permissions should be checked.

```graphql
type Query {
  post(id: ID @eq): Post @can(ability: "view", find: "id")
}
```

```php
class PostPolicy
{
    public function update(User $user, Post $post): bool
    {
        return $user->id === $post->author_id;
    }
}
```

The name of the returned Type `Post` is used as the Model class, however you may overwrite this by
passing the `model` argument.

```graphql
type Mutation {
  createBlogPost(input: PostInput): BlogPost
    @can(ability: "create", model: "App\\Post")
}
```

You can pass additional arguments to the policy checks by specifying them as `args`.

```graphql
type Mutation {
  createPost(input: PostInput): Post
    @can(ability: "create", args: ["FROM_GRAPHQL"])
}
```

Starting from Laravel 5.7, [authorization of guest users](https://laravel.com/docs/authorization#guest-users) is supported.
Because of this, Lighthouse does **not** validate that the user is authenticated before passing it along to the policy.

## @complexity

Perform calculation of a fields complexity score before execution.

```graphql
type Query {
  posts: [Post!]! @complexity
}
```

[Read More about query complexity analysis](http://webonyx.github.io/graphql-php/security/#query-complexity-analysis)

### Definition

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
  resolver: String
) on FIELD_DEFINITION
```

### Examples

You can provide your own function to calculate complexity.

```graphql
type Query {
  posts: [Post!]!
    @complexity(resolver: "App\\Security\\ComplexityAnalyzer@userPosts")
}
```

A custom complexity function may look like the following,
refer to the [complexity function signature](resolvers.md#complexity-function-signature).

```php
namespace App\Security;

class ComplexityAnalyzer {

    public function userPosts(int $childrenComplexity, array $args): int
    {
        $postComplexity = $args['includeFullText'])
            ? 3
            : 2;

        return $childrenComplexity * $postComplexity;
    }
```

## @create

Create a new Eloquent model with the given arguments.

```graphql
type Mutation {
  createPost(title: String!): Post @create
}
```

### Definition

```graphql
"""
Create a new Eloquent model with the given arguments.
"""
directive @create(
  """
  Specify the class name of the model to use.
  This is only needed when the default model resolution does not work.
  """
  model: String
) on FIELD_DEFINITION
```

### Examples

If you are using a single input object as an argument, you must tell Lighthouse
to spread out the nested values before applying it to the resolver.

```graphql
type Mutation {
  createPost(input: CreatePostInput! @spread): Post @create
}

input CreatePostInput {
  title: String!
}
```

If the name of the Eloquent model does not match the return type of the field,
or is located in a non-default namespace, set it with the `model` argument.

```graphql
type Mutation {
  createPost(title: String!): Post @create(model: "Foo\\Bar\\MyPost")
}
```

## @delete

Delete one or more models by their ID.

```graphql
type Mutation {
  deletePost(id: ID!): Post @delete
}
```

### Definition

```graphql
"""
Delete one or more models by their ID.
The field must have an single non-null argument that may be a list.
"""
directive @delete(
  """
  Set to `true` to use global ids for finding the model.
  If set to `false`, regular non-global ids are used.
  """
  globalId: Boolean = false
) on FIELD_DEFINITION
```

### Examples

If you use global ids, you can set the `globalId` argument to `true`.
Lighthouse will decode the id for you automatically.

```graphql
type Mutation {
  deletePost(id: ID!): Post @delete(globalId: true)
}
```

You can also delete multiple models at once.
Define a field that takes a list of IDs and returns a Collection of the
deleted models.

```graphql
type Mutation {
  deletePosts(id: [ID!]!): [Post!]! @delete
}
```

If the name of the Eloquent model does not match the return type of the field,
or is located in a non-default namespace, set it with the `model` argument.

```graphql
type Mutation {
  deletePost(id: ID!): Post @delete(model: "Bar\\Baz\\MyPost")
}
```

## @deprecated

You can mark fields as deprecated by adding the `@deprecated` directive and providing a
`reason`. Deprecated fields are not included in introspection queries unless
requested and they can still be queried by clients.

```graphql
type Query {
  users: [User] @deprecated(reason: "Use the `allUsers` field")
  allUsers: [User]
}
```

### Definition

```graphql
"""
Marks an element of a GraphQL schema as no longer supported.
"""
directive @deprecated(
  """
  Explains why this element was deprecated, usually also including a
  suggestion for how to access supported similar data. Formatted
  in [Markdown](https://daringfireball.net/projects/markdown/).
  """
  reason: String = "No longer supported"
) on FIELD_DEFINITION
```

## @field

Assign a resolver function to a field.

Pass a class and a method to the `resolver` argument and separate them with an `@` symbol.
If you pass only a class name, the method name defaults to `__invoke`.

```graphql
type Mutation {
  createPost(title: String!): Post
    @field(resolver: "App\\GraphQL\\Mutations\\PostMutator@create")
}
```

### Definition

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

  """
  Supply additional data to the resolver.
  """
  args: [String!]
) on FIELD_DEFINITION
```

### Examples

If your field is defined on the root types `Query` or `Mutation`, you can take advantage
of the default namespaces that are defined in the [configuration](../getting-started/configuration.md). The following
will look for a class in `App\GraphQL\Queries` by default.

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

Find a model based on the arguments provided.

```graphql
type Query {
  userById(id: ID! @eq): User @find
}
```

### Definition

```graphql
"""
Find a model based on the arguments provided.
"""
directive @find(
  """
  Specify the class name of the model to use.
  This is only needed when the default model resolution does not work.
  """
  model: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) on FIELD_DEFINITION
```

### Examples

This throws when more then one result is returned.
Use [@first](#first) if you can not ensure that.

If your model does not sit in the default namespace, you can overwrite it.

```graphql
type Query {
  userById(id: ID! @eq): User @find(model: "App\\Authentication\\User")
}
```

## @first

Get the first query result from a collection of Eloquent models.

```graphql
type Query {
  userByFirstName(first_name: String! @eq): User @first
}
```

### Definition

```graphql
"""
Get the first query result from a collection of Eloquent models.
"""
directive @first(
  """
  Specify the class name of the model to use.
  This is only needed when the default model resolution does not work.
  """
  model: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) on FIELD_DEFINITION
```

### Examples

Other then [@find](#find), this will not throw an error if more then one items are in the collection.

If your model does not sit in the default namespace, you can overwrite it.

```graphql
type Query {
  userByFirstName(first_name: String! @eq): User
    @first(model: "App\\Authentication\\User")
}
```

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
  You can use any constant literal value: https://graphql.github.io/graphql-spec/draft/#sec-Input-Values
  """
  value: Mixed
) on ENUM_VALUE
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

Place an equal operator on an Eloquent query.

```graphql
type User {
  posts(category: String @eq): [Post!]! @hasMany
}
```

### Definition

```graphql
directive @eq(
  """
  Specify the database column to compare.
  Only required if database column has a different name than the attribute in your schema.
  """
  key: String
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

### Examples

If the name of the argument does not match the database column,
pass the actual column name as the `key`.

```graphql
type User {
  posts(category: String @eq(key: "cat")): [Post!]! @hasMany
}
```

## @event

Fire an event after a mutation has taken place.
It requires the `dispatch` argument that should be
the class name of the event you want to fire.

```graphql
type Mutation {
  createPost(title: String!, content: String!): Post
    @event(dispatch: "App\\Events\\PostCreated")
}
```

### Definition

```graphql
"""
Fire an event after a mutation has taken place.
It requires the `dispatch` argument that should be
the class name of the event you want to fire.
"""
directive @event(
  """
  Specify the fully qualified class name (FQCN) of the event to dispatch.
  """
  dispatch: String!
) on FIELD_DEFINITION
```

## @globalId

Converts between IDs/types and global IDs.

```graphql
type User {
  id: ID! @globalId
  name: String
}
```

Instead of the original ID, the `id` field will now return a base64-encoded String
that globally identifies the User and can be used for querying the `node` endpoint.

### Definition

```graphql
"""
Converts between IDs/types and global IDs.
When used upon a field, it encodes,
when used upon an argument, it decodes.
"""
directive @globalId(
  """
  By default, an array of `[$type, $id]` is returned when decoding.
  You may limit this to returning just one of both.
  Allowed values: "ARRAY", "TYPE", "ID"
  """
  decode: String = "ARRAY"
) on FIELD_DEFINITION | INPUT_FIELD_DEFINITION | ARGUMENT_DEFINITION
```

### Examples

```graphql
type Mutation {
  deleteNode(id: ID @globalId): Node
}
```

The field resolver will receive the decoded version of the passed `id`,
split into type and ID.

You may rebind the `\Nuwave\Lighthouse\Support\Contracts\GlobalId` interface to add your
own mechanism of encoding/decoding global ids.

## @hasMany

Corresponds to [the Eloquent relationship HasMany](https://laravel.com/docs/eloquent-relationships#one-to-many).

```graphql
type User {
  posts: [Post!]! @hasMany
}
```

### Definition

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
  ALlows to resolve the relation as a paginated list.
  Allowed values: paginator, connection.
  """
  type: String

  """
  Specify the default quantity of elements to be returned.
  Only applies when using pagination.
  """
  defaultCount: Int

  """
  Specify the maximum quantity of elements to be returned.
  Only applies when using pagination.
  """
  maxCount: Int
) on FIELD_DEFINITION
```

### Examples

You can return the related models paginated by setting the `type`.

```graphql
type User {
  postsPaginated: [Post!]! @hasMany(type: "paginator")
  postsRelayConnection: [Post!]! @hasMany(type: "connection")
}
```

If the name of the relationship on the Eloquent model is different than the field name,
you can override it by setting `relation`.

```graphql
type User {
  posts: [Post!]! @hasMany(relation: "articles")
}
```

## @hasOne

Corresponds to [Eloquent's HasOne-Relationship](https://laravel.com/docs/eloquent-relationships#one-to-one).

```graphql
type User {
  phone: Phone @hasOne
}
```

### Definition

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

### Examples

If the name of the relationship on the Eloquent model is different than the field name,
you can override it by setting `relation`.

```graphql
type User {
  phone: Phone @hasOne(relation: "telephone")
}
```

## @in

Filter a column by an array using a `whereIn` clause.

```graphql
type Query {
  posts(includeIds: [Int!] @in(key: "id")): [Post!]! @paginate
}
```

### Definition

```graphql
directive @in(
  """
  Specify the database column to compare.
  Only required if database column has a different name than the attribute in your schema.
  """
  key: String
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

## @include

This directive is part of the [GraphQL spec](https://graphql.github.io/graphql-spec/June2018/#sec--include)
and it should be noted this directive is a client side and should not be included in your schema.

Only includes a field in response if the value passed into this directive is true. This directive is one of the core
directives in the GraphQL spec.

```graphql
directive @include(
  """
  If the "if" value is true the field this is connected with will be included in the query response.
  Otherwise it will not.
  """
  if: Boolean
) on FIELD | FRAGMENT_SPREAD | INLINE_FRAGMENT
```

### Examples

The `@include` directive may be provided for fields, fragment spreads, and inline fragments,
and allows for conditional inclusion during execution as described by the `if` argument.

In this example experimentalField will only be queried if the variable \$someTest has the value true

```graphql
query myQuery($someTest: Boolean) {
  experimentalField @include(if: $someTest)
}
```

## @inject

Inject a value from the context object into the arguments.

```graphql
type Mutation {
  createPost(title: String!, content: String!): Post
    @create
    @inject(context: "user.id", name: "user_id")
}
```

This is useful to ensure that the authenticated user's `id` is
automatically used for creating new models and can not be manipulated.

### Definition

```graphql
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
) on FIELD_DEFINITION
```

### Examples

If you are using an Input Object as an argument, you can use dot notation to
set a nested argument.

```graphql
type Mutation {
  createTask(input: CreateTaskInput!): Task
    @create
    @inject(context: "user.id", name: "input.user_id")
}
```

## @interface

Use a custom resolver to determine the concrete type of an interface.

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
<?php

namespace App\GraphQL\Interfaces;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class Commentable
{
    /**
     * @var \Nuwave\Lighthouse\Schema\TypeRegistry
     */
    protected $typeRegistry;

    public function __construct(TypeRegistry $typeRegistry)
    {
        $this->typeRegistry = $typeRegistry;
    }

    /**
     * Decide which GraphQL type a resolved value has.
     *
     * @param  mixed  $rootValue  The value that was resolved by the field. Usually an Eloquent model.
     * @param  \Nuwave\Lighthouse\Support\Contracts\GraphQLContext  $context
     * @param  \GraphQL\Type\Definition\ResolveInfo  $resolveInfo
     * @return \GraphQL\Type\Definition\Type
     */
    public function resolveType($rootValue, GraphQLContext $context, ResolveInfo $resolveInfo): Type
    {
        // Default to getting a type with the same name as the passed in root value
        // TODO implement your own resolver logic - if the default is fine, just delete this class
        return $this->typeRegistry->get(class_basename($rootValue));
    }
}
```

### Definition

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
) on FIELD_DEFINITION
```

This is often useful when loading relationships with the [`@hasMany`](#hasmany) directive.

```graphql
type Post {
  comments: [Comment!]! @hasMany @lazyLoad(relations: ["replies"])
}
```

## @method

Call a method with a given `name` on the class that represents a type to resolve a field.
Use this if the data is not accessible as an attribute (e.g. `$model->myData`).

```graphql
type User {
  mySpecialData: String! @method(name: "findMySpecialData")
}
```

This calls a method `App\User::findMySpecialData` with [the typical resolver arguments](resolvers.md#resolver-function-signature).

The first argument is an instance of the class itself,
so the method can be `public static` if needed.

### Definition

```graphql
"""
Call a method with a given `name` on the class that represents a type to resolve a field.
Use this if the data is not accessible as an attribute (e.g. `$model->myData`).
"""
directive @method(
  """
  Specify the method of which to fetch the data from.
  """
  name: String
) on FIELD_DEFINITION
```

## @middleware

```graphql
"""
Run Laravel middleware for a specific field or group of fields.
This can be handy to reuse existing HTTP middleware.
"""
directive @middleware(
  """
  Specify which middleware to run.
  Pass in either a fully qualified class name, an alias or
  a middleware group - or any combination of them.
  """
  checks: [String!]
) on FIELD_DEFINITION | OBJECT
```

You can define middleware just like you would in Laravel. Pass in either a fully qualified
class name, an alias or a middleware group - or any combination of them.

```graphql
type Query {
  users: [User!]!
    @middleware(
      checks: ["auth:api", "App\\Http\\Middleware\\MyCustomAuth", "api"]
    )
    @all
}
```

If you need to apply middleware to a group of fields, you can put [@middleware](../api-reference/directives.md#middleware) on an Object type.
The middleware will apply only to direct child fields of the type definition.

```graphql
type Query @middleware(checks: ["auth:api"]) {
  # This field will use the "auth:api" middleware
  users: [User!]! @all
}

extend type Query {
  # This field will not use any middleware
  posts: [Post!]! @all
}
```

Other then global middleware defined in the [configuration](../getting-started/configuration.md), field middleware
only applies to the specific field it is defined on. This has the benefit of limiting errors
to particular fields and not failing an entire request if a middleware fails.

There are a few caveats to field middleware though:

- The Request object is shared between fields.
  If the middleware of one field modifies the Request, this does influence other fields.
- They not receive the complete Response object when calling `$next($request)`,
  but rather the slice of data that the particular field returned.
- The `terminate` method of field middleware is not called.

If the middleware needs to be aware of GraphQL specifics, such as the resolver arguments,
it is often more suitable to define a custom field directive.

## @model

Enable fetching an Eloquent model by its global id through the `node` query.

```graphql
type User @model {
  id: ID! @globalId
}
```

Behind the scenes, Lighthouse will decode the global id sent from the client
to find the model by it's primary id in the database.

You may rebind the `\Nuwave\Lighthouse\Support\Contracts\GlobalId` interface to add your
own mechanism of encoding/decoding global ids.

### Definition

```graphql
"""
Enable fetching an Eloquent model by its global id through the `node` query.
"""
directive @model on OBJECT
```

## @morphMany

Corresponds to [Eloquent's MorphMany-Relationship](https://laravel.com/docs/5.8/eloquent-relationships#one-to-many-polymorphic-relations).

```graphql
type Post {
  images: [Image!] @morphMany
}

type Image {
  imagable: Imageable! @morphTo
}

union Imageable = Post | User
```

### Definition

```graphql
"""
Corresponds to [Eloquent's MorphMany-Relationship](https://laravel.com/docs/5.8/eloquent-relationships#one-to-one-polymorphic-relations).
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
  ALlows to resolve the relation as a paginated list.
  Allowed values: paginator, connection.
  """
  type: String

  """
  Specify the default quantity of elements to be returned.
  Only applies when using pagination.
  """
  defaultCount: Int

  """
  Specify the maximum quantity of elements to be returned.
  Only applies when using pagination.
  """
  maxCount: Int

  """
  Specify a custom type that implements the Edge interface
  to extend edge object.
  Only applies when using Relay style "connection" pagination.
  """
  edgeType: String
) on FIELD_DEFINITION
```

## @morphOne

Corresponds to [Eloquent's MorphOne-Relationship](https://laravel.com/docs/5.8/eloquent-relationships#one-to-one-polymorphic-relations).

```graphql
type Post {
  image: Image! @morphOne
}

type Image {
  imagable: Imageable! @morphTo
}

union Imageable = Post | User
```

### Definition

```graphql
"""
Corresponds to [Eloquent's MorphOne-Relationship](https://laravel.com/docs/5.8/eloquent-relationships#one-to-one-polymorphic-relations).
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

## @morphTo

Corresponds to [Eloquent's MorphTo-Relationship](https://laravel.com/docs/5.8/eloquent-relationships#one-to-one-polymorphic-relations).

```graphql
type Image {
  imagable: Imageable! @morphTo
}

union Imageable = Post | User
```

### Definition

```graphql
"""
Corresponds to [Eloquent's MorphTo-Relationship](https://laravel.com/docs/5.8/eloquent-relationships#one-to-one-polymorphic-relations).
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

## @namespace

Redefine the default namespaces used in other directives.

The following example applies the namespace `App\Blog`
to the `@field` directive used on the `posts` field.

```graphql
type Query {
  posts: [Post!]!
    @field(resolver: "Post@resolveAll")
    @namespace(field: "App\\Blog")
}
```

### Definition

```graphql
"""
Redefine the default namespaces used in other directives.
The arguments are a map from directive names to namespaces.
"""
directive @namespace on FIELD_DEFINITION | OBJECT
```

### Examples

When used upon an object type or an object type extension, the namespace
applies to fields of the type as well. This allows you to specify
a common namespace for a group of fields.

```graphql
extend type Query @namespace(field: "App\\Blog") {
  posts: [Post!]! @field(resolver: "Post@resolveAll")
}
```

A `@namespace` directive defined on a field directive wins in case of a conflict.

## @neq

Place a not equals operator `!=` on an Eloquent query.

```graphql
type User {
  posts(excludeCategory: String @neq(key: "category")): [Post!]! @hasMany
}
```

### Definition

```graphql
"""
Place a not equals operator `!=` on an Eloquent query.
"""
directive @neq(
  """
  Specify the database column to compare.
  Only required if database column has a different name than the attribute in your schema.
  """
  key: String
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

## @node

Register a type for relay global object identification.

```graphql
type User @node(resolver: "App\\GraphQL\\NodeResolver@resolveUser") {
  name: String!
}
```

The `resolver` argument has to specify a function which will be passed the
decoded `id` and resolves to a result.

```php
function resolveUser($id): \App\User
```

Note: if you plan on resolving using an Eloquent Model, be sure to check out the [`@model`](#model) directive.

### Definition

```graphql
"""
Register a type for relay global object identification.
"""
directive @node(
  """
  Reference to resolver function.
  Consists of two parts: a class name and a method name, seperated by an `@` symbol.
  If you pass only a class name, the method name defaults to `__invoke`.
  """
  resolver: String!
) on FIELD_DEFINITION
```

## @notIn

Filter a column by an array using a `whereNotIn` clause.

```graphql
type Query {
  posts(excludeIds: [Int!] @notIn(key: "id")): [Post!]! @paginate
}
```

### Definition

```graphql
"""
Filter a column by an array using a `whereNotIn` clause.
"""
directive @notIn(
  """
  Specify the name of the column.
  Only required if it differs from the name of the argument.
  """
  key: String
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

## @orderBy

Sort a result list by one or more given fields.

```graphql
type Query {
  posts(orderBy: [OrderByClause!] @orderBy): [Post!]!
}
```

### Definition

```graphql
directive @orderBy on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

The `OrderByClause` input is automatically added to the schema,
together with the `SortOrder` enum.

```graphql
input OrderByClause {
  field: String!
  order: SortOrder!
}

enum SortOrder {
  ASC
  DESC
}
```

Querying a field that has an `orderBy` argument looks like this:

```graphql
{
  posts(orderBy: [{ field: "postedAt", order: ASC }]) {
    title
  }
}
```

You may pass more than one sorting option to add a secondary ordering.

### Input Definition Example

The `@orderBy` directive can also be applied inside an input field definition when used in conjunction with the [`@spread`](#spread) directive. See below for example:

```graphql
type Query {
  posts(filter: PostFilterInput @spread): Posts
}

input PostFilterInput {
  orderBy: [OrderByClause!] @orderBy
}
```

And usage example:

```graphql
{
  posts(filter: { orderBy: [{ field: "postedAt", order: ASC }] }) {
    title
  }
}
```

## @paginate

Query multiple entries as a paginated list.

```graphql
type Query {
  posts: [Post!]! @paginate
}
```

The schema definition is automatically transformed to this:

```graphql
type Query {
  posts(first: Int!, page: Int): PostPaginator
}

type PostPaginator {
  data: [Post!]!
  paginatorInfo: PaginatorInfo!
}
```

And can be queried like this:

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

### Definition

```graphql
"""
Query multiple entries as a paginated list.
"""
directive @paginate(
  """
  Which pagination style to use.
  Allowed values: paginator, connection.
  """
  type: String = "paginator"

  """
  Specify the class name of the model to use.
  This is only needed when the default model resolution does not work.
  """
  model: String

  """
  Point to a function that provides a Query Builder instance.
  This replaces the use of a model.
  """
  builder: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]

  """
  Overwrite the paginate_max_count setting value to limit the
  amount of items that a user can request per page.
  """
  maxCount: Int

  """
  Use a default value for the amount of returned items
  in case the client does not request it explicitly
  """
  defaultCount: Int
) on FIELD_DEFINITION
```

### Examples

The `type` of pagination defaults to `paginator`, but may also be set to a Relay
compliant `connection`.

```graphql
type Query {
  posts: [Post!]! @paginate(type: "connection")
}
```

You can supply a `defaultCount` to set a default count for any kind of paginator.

```graphql
type Query {
  posts: [Post!]! @paginate(type: "connection", defaultCount: 25)
}
```

This let's you omit the `count` argument when querying:

```graphql
query {
  posts {
    id
    name
  }
}
```

Lighthouse allows you to specify a global maximum for the number of items a user
can request through pagination through the config. You may also overwrite this
per field with the `maxCount` argument:

```graphql
type Query {
  posts: [Post!]! @paginate(maxCount: 10)
}
```

By default, Lighthouse looks for an Eloquent model in the configured default namespace, with the same
name as the returned type. You can overwrite this by setting the `model` argument.

```graphql
type Query {
  posts: [Post!]! @paginate(model: "App\\Blog\\BlogPost")
}
```

If simply querying Eloquent does not fit your use-case, you can specify a custom `builder`.

```graphql
type Query {
  posts: [Post!]! @paginate(builder: "App\\Blog@visiblePosts")
}
```

Your method receives the typical resolver arguments and has to return an instance of `Illuminate\Database\Query\Builder`.

```php
<?php

namespace App;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class Blog
{
    public function visiblePosts($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Builder
    {
        return DB::table('posts')
            ->where('visible', true)
            ->where('posted_at', '>', $args['after']);
    }
}
```

## @rename

Rename a field on the server side, e.g. convert from snake_case to camelCase.

```graphql
type User {
  createdAt: String! @rename(attribute: "created_at")
}
```

### Definition

```graphql
directive @rename(
  """
  Specify the original name of the property/key that the field
  value can be retrieved from.
  """
  attribute: String!
) on FIELD_DEFINITION
```

## @rules

Validate an argument using [Laravel built-in validation](https://laravel.com/docs/validation).

```graphql
type Query {
  users(countryCode: String @rules(apply: ["string", "size:2"])): [User!]! @all
}
```

Read more in the [validation docs](../security/validation.md#validating-arguments).

### Definition

```graphql
"""
Validate an argument using [Laravel built-in validation](https://laravel.com/docs/validation).
"""
directive @rules(
  """
  Specify the validation rules to apply to the field.
  This can either be a reference to any of Laravel's built-in validation rules: https://laravel.com/docs/validation#available-validation-rules,
  or the fully qualified class name of a custom validation rule.
  """
  apply: [String!]!

  """
  Specify the messages to return if the validators fail.
  Specified as an input object that maps rules to messages,
  e.g. { email: "Must be a valid email", max: "The input was too long" }
  """
  messages: [RulesMessageMap!]
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

## @rulesForArray

Run validation on an array itself, using [Laravel built-in validation](https://laravel.com/docs/validation).

```graphql
type Mutation {
  saveIcecream(
    flavors: [IcecreamFlavor!]! @rulesForArray(apply: ["min:3"])
  ): Icecream
}
```

Read more in the [validation docs](../security/validation.md#validating-arrays).

### Definition

```graphql
"""
Run validation on an array itself, using [Laravel built-in validation](https://laravel.com/docs/validation).
"""
directive @rulesForArray(
  """
  Specify the validation rules to apply to the field.
  This can either be a reference to any of Laravel's built-in validation rules: https://laravel.com/docs/validation#available-validation-rules,
  or the fully qualified class name of a custom validation rule.
  """
  apply: [String!]!

  """
  Specify the messages to return if the validators fail.
  Specified as an input object that maps rules to messages,
  e.g. { email: "Must be a valid email", max: "The input was too long" }
  """
  messages: [RulesMessageMap!]
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

## @scalar

Reference a class implementing a scalar definition.
[Learn how to implement your own scalar.](http://webonyx.github.io/graphql-php/type-system/scalar-types/)

```graphql
scalar DateTime @scalar(class: "DateTimeScalar")
```

If you follow the namespace convention, you do not need this directive.
Lighthouse looks into your configured scalar namespace for a class with the same name.

### Definition

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

### Examples

If your class is not in the default namespace, pass a fully qualified class name.

```graphql
scalar DateTime
  @scalar(class: "Nuwave\\Lighthouse\\Schema\\Types\\Scalars\\DateTime")
```

## @search

Perform a full-text by the given input value.

```graphql
type Query {
  posts(search: String @search): [Post!]! @paginate
}
```

The `search()` method of the model is called with the value of the argument,
using the driver you configured for [Laravel Scout](https://laravel.com/docs/master/scout).

Take care when using the `@search` directive in combination with other directives
that influence the database query. The usual query builder `Eloquent\Builder`
will be replaced by a `Scout\Builder`, which does not support the same methods and operations.
Regular filters such as [`@eq`](#eq) or [`@in`](#in) still work, but scopes do not.

### Definition

```graphql
"""
Perform a full-text by the given input value.
"""
directive @search(
  """
  Specify a custom index to use for search.
  """
  within: String
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

### Examples

Normally the search will be performed using the index specified by the model's `searchableAs` method.
However, in some situation a custom index might be needed, this can be achieved by using the argument `within`.

```graphql
type Query {
  posts(search: String @search(within: "my.index")): [Post!]! @paginate
}
```

## @skip

This directive is part of the [GraphQL spec](https://graphql.github.io/graphql-spec/June2018/#sec--include)
and it should be noted this directive is a client side directive and should not be included in your schema.

### Definition

```graphql
directive @skip(
  """
  If the value passed into the if field is true the field this
  is decorating will not be included in the query response.
  """
  if: Boolean!
) on FIELD | FRAGMENT_SPREAD | INLINE_FRAGMENT
```

### Examples

The `@skip` directive may be provided for fields, fragment spreads, and inline fragments, and allows for conditional
exclusion during execution as described by the if argument.

In this example experimentalField will only be queried if the variable \$someTest has the value `false`.

```graphql
query myQuery($someTest: Boolean) {
  experimentalField @skip(if: $someTest)
}
```

## @spread

Spread out the nested values of an argument of type input object into it's parent.

```graphql
type Mutation {
  updatePost(id: ID!, input: PostInput! @spread): Post @update
}

input PostInput {
  title: String!
  body: String
}
```

The schema does not change, client side usage works the same:

```graphql
mutation {
  updatePost(id: 12, input: { title: "My awesome title" }) {
    id
  }
}
```

Internally, the arguments will be transformed into a flat structure before
they are passed along to the resolver:

```php
[
    'id' => 12
    'title' = 'My awesome title'
]
```

Note that Lighthouse spreads out the arguments **after** all other `ArgDirectives` have
been applied, e.g. validation, transformation.

### Definition

```graphql
"""
Spread out the nested values of an argument of type input object into it's parent.
"""
directive @spread on ARGUMENT_DEFINITION
```

## @subscription

Reference a class to handle the broadcasting of a subscription to clients.
The given class must extend `\Nuwave\Lighthouse\Schema\Types\GraphQLSubscription`.

If you follow the default naming conventions for [defining subscription fields](../subscriptions/defining-fields.md)
you do not need this directive. It is only useful if you need to override the default namespace.

```graphql
type Subscription {
  postUpdated(author: ID!): Post
    @subscription(class: "App\\GraphQL\\Blog\\PostUpdatedSubscription")
}
```

### Definition

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

## @trim

Run the `trim` function on an input value.

```graphql
type Mutation {
  createUser(name: String @trim): User
}
```

### Definition

```graphql
"""
Run the `trim` function on an input value.
"""
directive @trim on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

## @union

Use a custom function to determine the concrete type of unions.

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
<?php

namespace App\GraphQL\Unions;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class Person
{
    /**
     * @var \Nuwave\Lighthouse\Schema\TypeRegistry
     */
    protected $typeRegistry;

    public function __construct(TypeRegistry $typeRegistry)
    {
        $this->typeRegistry = $typeRegistry;
    }

    /**
     * Decide which GraphQL type a resolved value has.
     *
     * @param  mixed  $rootValue The value that was resolved by the field. Usually an Eloquent model.
     * @param  \Nuwave\Lighthouse\Support\Contracts\GraphQLContext  $context
     * @param  \GraphQL\Type\Definition\ResolveInfo  $resolveInfo
     * @return \GraphQL\Type\Definition\Type
     */
    public function resolveType($rootValue, GraphQLContext $context, ResolveInfo $resolveInfo): Type
    {
        // Default to getting a type with the same name as the passed in root value
        // TODO implement your own resolver logic - if the default is fine, just delete this class
        return $this->typeRegistry->get(class_basename($rootValue));
    }
}
```

### Definition

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

## @update

Update an Eloquent model with the input values of the field.

```graphql
type Mutation {
  updatePost(id: ID!, content: String): Post @update
}
```

### Definition

```graphql
"""
Update an Eloquent model with the input values of the field.
"""
directive @update(
  """
  Specify the class name of the model to use.
  This is only needed when the default model resolution does not work.
  """
  model: String

  """
  Set to `true` to use global ids for finding the model.
  If set to `false`, regular non-global ids are used.
  """
  globalId: Boolean = false
) on FIELD_DEFINITION
```

### Examples

Lighthouse uses the argument `id` to fetch the model by its primary key.
This will work even if your model has a differently named primary key,
so you can keep your schema simple and independent of your database structure.

If you want your schema to directly reflect your database schema,
you can also use the name of the underlying primary key.
This is not recommended as it makes client-side caching more difficult
and couples your schema to the underlying implementation.

```graphql
type Mutation {
  updatePost(post_id: ID!, content: String): Post @update
}
```

If the name of the Eloquent model does not match the return type of the field,
or is located in a non-default namespace, set it with the `model` argument.

```graphql
type Mutation {
  updateAuthor(id: ID!, name: String): Author @update(model: "App\\User")
}
```

## @where

Use an input value as a [where filter](https://laravel.com/docs/queries#where-clauses).

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

### Definition

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
  """
  clause: String
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

## @whereBetween

Verify that a column's value is between two values.

```graphql
type Query {
  posts(created_at: DateRange @whereBetween): [Post!]! @all
}

input DateRange {
  from: Date!
  to: Date!
}
```

The type of the input value this is defined upon should be
an `input` object with two fields.

### Definition

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
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

## @whereConstraints

Add a dynamically client-controlled WHERE constraint to a fields query.

### Definition

```graphql
"""
Add a dynamically client-controlled WHERE constraint to a fields query.
The argument it is defined on may have any name but **must** be
of the input type `WhereConstraints`.
"""
directive @whereConstraints(
  """
  Restrict the allowed column names to a well-defined list.
  This improves introspection capabilities and security.
  """
  columns: [String!]
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

### Setup

**This is an experimental feature and not included in Lighthouse by default.**

Add the service provider to your `config/app.php`

```php
'providers' => [
    \Nuwave\Lighthouse\WhereConstraints\WhereConstraintsServiceProvider::class,
],
```

Install the dependency [mll-lab/graphql-php-scalars](https://github.com/mll-lab/graphql-php-scalars):

    composer require mll-lab/graphql-php-scalars

It contains the scalar type `Mixed`, which enables the dynamic query capabilities.

```graphql
scalar Mixed @scalar(class: "MLL\\GraphQLScalars\\Mixed")
```

Add an enum type `Operator` to your schema. Depending on your
database, you may want to allow different internal values. This default
should work for most databases:

```graphql
enum Operator {
  EQ @enum(value: "=")
  NEQ @enum(value: "!=")
  GT @enum(value: ">")
  GTE @enum(value: ">=")
  LT @enum(value: "<")
  LTE @enum(value: "<=")
  LIKE @enum(value: "LIKE")
  NOT_LIKE @enum(value: "NOT_LIKE")
}
```

### Usage

```graphql
type Query {
  people(
    where: WhereConstraints
      @whereConstraints(columns: ["age", "type", "haircolour", "height"])
  ): [Person!]!
}
```

This is how you can use it to construct a complex query
that gets actors over age 37 who either have red hair or are at least 150cm.

```graphql
{
  people(
    filter: {
      where: [
        {
          AND: [
            { column: AGE, operator: GT, value: 37 }
            { column: TYPE, value: "Actor" }
            {
              OR: [
                { column: HAIRCOLOUR, value: "red" }
                { column: HEIGHT, operator: GTE, value: 150 }
              ]
            }
          ]
        }
      ]
    }
  ) {
    name
  }
}
```

Lighthouse generates definitions for an `Enum` type and an `Input` type
that are restricted to the defined columns.

```graphql
"Dynamic WHERE constraints for the `where` argument on the query `people`.
input PeopleWhereWhereConstraints {
    column: PeopleWhereColumn
    operator: String = EQ
    value: Mixed
    AND: [PeopleWhereWhereConstraints!]
    OR: [PeopleWhereWhereConstraints!]
    NOT: [PeopleWhereWhereConstraints!]
}

"Allowed column names for the `where` argument on the query `people`."
enum PeopleWhereColumn {
    AGE @enum(value: "age")
    TYPE @enum(value: "type")
    HAIRCOLOUR @enum(value: "haircolour")
    HEIGHT @enum(value: "height")
}
```

When you are not specifying `columns` to allow, a generic input with dynamic
column names will be used instead.

## @whereJsonContains

Use an input value as a [whereJsonContains filter](https://laravel.com/docs/queries#json-where-clauses).

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

### Definition

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
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

## @whereNotBetween

Verify that a column's value lies outside of two values.
The type of the input value this is defined upon should be
an `input` object with two fields.

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

### Definition

```graphql
"""
Verify that a column's value lies outside of two values.
The type of the input value this is defined upon should be
an `input` object with two fields.
"""
directive @whereNotBetween(
  """
  Specify the database column to compare.
  Only required if database column has a different name than the attribute in your schema.
  """
  key: String
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION
```

## @with

Eager-load an Eloquent relation.

```graphql
type User {
  taskSummary: String! @with(relation: "tasks") @method(name: "getTaskSummary")
}
```

### Definition

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
) on FIELD_DEFINITION
```

This can be a useful optimization for fields that are not returned directly
but rather used for resolving other fields.

If you just want to return the relation itself as-is,
look into [handling Eloquent relationships](../eloquent/relationships.md).
