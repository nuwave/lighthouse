# Directives

## @auth

Return the currently authenticated user as the result of a query.

```graphql
type Query {
  me: User @auth
}
```

## @all

Fetch all Eloquent models and return the collection as the result for a field.

```graphql
type Query {
  users: [User!]! @all
}
```

This assumes your model has the same name as the type you are returning and is defined
in the default model namespace `App\Models`. [You can change this configuration](../getting-started/configuration.md).

If you need to use a different model for a single field, you can pass a class name as the `model` argument.

```graphql
type Query {
  posts: [Post!]! @all(model: "App\\Blog\\BlogEntry")
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

namespace App\Models;

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

namespace App\Models;

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

The directive accepts an optional `relation` argument if your relationship method
has a different name than the field.

```graphql
type User {
  jobs: [Role!]! @belongsToMany(relation: "roles")
}
```

## @bcrypt

Run the `bcrypt` function on the argument it is defined on.

```graphql
type Mutation {
  createUser(name: String, password: String @bcrypt): User
}
```

When you resolve the field, the argument will hold the `bcrypt` value.

```php
<?php

namespace App\Http\GraphQL\Mutations;

class CreateUser
{
    public function resolve($root, array $args)
    {
        return User::create([
          'name' => $args['name'],
          // This will be the bcrypt value of the password argument
          'password' => $args['password']
        ]);
    }
}
```

## @cache

Cache the result of a resolver.

The cache is created on the first request and is cached forever by default.
Use this for values that change seldomly and take long to fetch/compute.

```graphql
type Query {
  highestKnownPrimeNumber: Int! @cache
}
```

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

When generating a cached result for a resolver, Lighthouse produces a unique key for each type. By default, Lighthouse will look for a field with the `ID` type to generate the key. If you'd like to use a different field (i.e., an external API id) you can mark the field with the `@cacheKey` directive.

```graphql
type GithubProfile {
  username: String @cacheKey
  repos: [Repository] @cache
}
```

## @can

Check a Laravel Policy to ensure the current user is authorized to access a field.
Set the name of the policy and the model to check against.

```graphql
type Mutation {
  createPost(input: PostInput): Post @can(if: "create", model: "App\\Post")
}
```

This is currently limited to doing [general checks on a resource and not a specific instance](https://laravel.com/docs/5.6/authorization#methods-without-models).
The defined functions receive the currently authenticated user.

```php
class PostPolicy
{
    public function create(User $user): bool
    {
        return $user->is_admin;
    }
}
```

## @complexity

Place on fields to perform analysis to calculate a query complexity score before execution. [Read More](http://webonyx.github.io/graphql-php/security/#query-complexity-analysis)

```graphql
type Query {
  posts: [Post!]! @complexity
}
```

You can provide your own function to calculate complexity.

```graphql
type Query {
  posts: [Post!]!
    @complexity(resolver: "App\\Security\\ComplexityAnalyzer@userPosts")
}
```

A custom complexity function may look like the following.
You may look up the [complexity function signature](resolvers.md#complexity-function-signature)

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

Applies to fields to create a new Eloquent model with the given arguments.

```graphql
type Mutation {
  createPost(title: String!): Post @create
}
```

## @delete

Delete a model with a given id field. The field must be an `ID` type.

```graphql
type Mutation {
  deletePost(id: ID!): Post @delete
}
```

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

## @field

Specify a custom resolver function for a single field.

In most cases, you do not even need this directive. Make sure you read about
the built in directives for [querying data](../the-basics/fields.md#query-data) and [mutating data](../the-basics/fields.md#mutate-data),
as well as the convention based approach to [implementing custom resolvers](../the-basics/fields.md#custom-resolvers).

Pass a class and a method to the `resolver` argument and separate them with an `@` symbol.

```graphql
type Mutation {
  createPost(title: String!): Post
    @field(resolver: "App\\Http\\GraphQL\\Mutations\\PostMutator@create")
}
```

If your field is defined on the root types `Query` or `Mutation`, you can take advantage
of the default namespaces that are defined in the [configuration](../getting-started/configuration.md). The following
will look for a class in `App\Http\GraphQL\Queries` by default.

```graphql
type Query {
  usersTotal: Int @field("Statistics@usersTotal")
}
```

Be aware that resolvers are not limited to root fields. A resolver can be used for basic tasks
such as transforming the value of scalar fields, e.g. reformat a date.

```graphql
type User {
  created_at: String!
    @field(resolver: "App\\Http\\GraphQL\\Types\\UserType@created_at")
}
```

## @find

Find a model based on the arguments provided.

```graphql
type Query {
  userById(id: ID! @eq): User @find(model: "App\\User")
}
```

This throws an error when more than one result is returned.
Use [@first](#first) if you can not ensure that.

## @first

Get the first query result from a collection of Eloquent models.

```graphql
type Query {
  userByFirstName(first_name: String! @eq): User @first(model: "App\\User")
}
```

Other than [@find](#find), this will not throw an error if more than one items are in the collection.

## @enum

Map the underlying value to an enum key. When dealing with the Enum type in your code,
you will receive the defined value instead of the string key.

```graphql
enum Role {
  ADMIN @enum(value: 1)
  EMPLOYEE @enum(value: 2)
}
```

## @eq

Place an equal operator on a eloquent query.

```graphql
type User {
  # this will filter a user's posts by the category.
  postsByCategory(category: String @eq): [Post] @hasMany
}
```

If the name of the argument does not match the database column,
pass the actual column name as the `key`.

```graphql
type User {
  postsByCategory(category: String @eq(key: "cat")): [Post] @hasMany
}
```

## @event

Fire an event after a mutation has taken place. It requires the `fire` argument that should be the class name of the event you want to fire.

```graphql
type Mutation {
  createPost(title: String!, content: String!): Post
    @event(fire: "App\\Events\\PostCreated")
}
```

## @globalId

Converts an ID to a global ID.

```graphql
type User {
  id: ID! @globalId
  name: String
}
```

Instead of the original ID, the `id` field will now return a base64-encoded String that globally identifies the User and can be used
for querying the `node` endpoint.

## @group

Apply common settings to all fields of an Object Type.

Set a common namespace for the [@field](#field) and the [@complexity](#complexity) directives
that are defined on the fields of the defined type.

```graphql
extend type Query @group(namespace: "App\\Models") {
  activeUsers @field(resolver: "User@getActiveUsers")
}
```

Set common middleware on a set of Queries/Mutations.

```graphql
type Mutation @group(middleware: ["api:auth"]) {
  createPost(title: String!): Post
}
```

## @hasMany

Corresponds to [Eloquent's HasMany-Relationship](https://laravel.com/docs/eloquent-relationships#one-to-many).

```graphql
type User {
  posts: [Post!]! @hasMany
}
```

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

If the name of the relationship on the Eloquent model is different than the field name,
you can override it by setting `relation`.

```graphql
type User {
  phone: Phone @hasOne(relation: "telephone")
}
```

## @in

Filter a column by an array.

```graphql
type Query {
  # this will filter a user's posts by the category id(s).
  postsByCategory(category_id: [Int] @in): [Post] @hasMany
}
```

## @inject

Inject a value from the context object into the arguments. This is really useful with the `@create` directive that rely on the authenticated user's `id` that you don't want the client to fill in themselves.

```graphql
type Mutation {
  createPost(title: String!, content: String!): Post
    @create(model: "App\\Post")
    @inject(context: "user.id", name: "user_id")
}
```

## @interface

Make sure you read the [basics about Interfaces](../the-basics/types.md#interface) before deciding
to use this directive, you probably don't need it.

You can point Lighthouse to a custom type resolver.
Set the `resolver` argument to a function that returns the implementing Object Type.

```graphql
interface Commentable
  @interface(resolver: "App\\GraphQL\\Interfaces\\Commentable@resolveType") {
  id: ID!
}
```

The function receives the value of the parent field as its single argument and must
return an Object Type. You can get the appropriate Object Type from Lighthouse's type registry.

```php
<?php

namespace App\Http\GraphQL\Interfaces;

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

## @method

Call a method on the target model.
This comes in handy if the data is not accessible as an attribute (e.g. `$model->myData`)
but rather via a method like `$model->myData()`. It requires the `name` argument.

```graphql
type User {
  mySpecialData: String! @method(name: "findMySpecialData")
}
```

## @middleware

Run Laravel middleware for a specific field. This can be handy to reuse existing
middleware.

```graphql
type Query {
  users: [User!]! @middleware(checks: ["auth:api"])
}
```

You can define middleware just like you would in Laravel. Pass in either a fully qualified
class name, an alias or a middleware group - or any combination of them.

```graphql
type Query {
  users: [User!]!
    @middleware(
      checks: ["auth:api", "App\\Http\\Middleware\\MyCustomAuth", "api"]
    )
}
```

If you need to apply middleware to a group of fields, you can put [@middleware](../api-reference/directives.md#middleware) on an Object type.
The middleware will apply only to direct child fields of the type definition.

```graphql
type Query @middleware(checks: ["auth:api"]) {
  # This field will use the "auth:api" middleware
  users: [User!]!
}

extend type Query {
  # This field will not use any middleware
  posts: [Post!]
}
```

Other than global middleware defined in the [configuration](../getting-started/configuration.md), field middleware
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

Enable fetching an Eloquent model by its global id, may be used for Relay.
Behind the scenes, Lighthouse will decode the global id sent from the client to find the model by it's primary id in the database.

```graphql
type User @model(class: "App\\User") {
  id: ID! @globalId
}
```

## @neq

Place a not equals operator `!=` on an Eloquent query.

```graphql
type User {
  # this will filter a user's posts that do not have the provided category.
  postsByCategory(category: String @neq): [Post] @hasMany
}
```

## @node

Store a type's resolver functions in Lighthouse's node registry.
The `resolver` argument has to specify a function which will be passed the
decoded `id` and resolves to a result.

```graphql
type User @node(resolver: "App\\GraphQL\\NodeResolver@resolveUser") {
  name: String!
}
```

```php
public function resolveUser(string $id): \App\User
```

The `typeResolver` is responsible for determining the GraphQL type the result
belongs to. Lighthouse provides a default implementation, but you can override
it if the need arises.

```graphql
type User
  @node(
    resolver: "App\\GraphQL\\NodeResolver@resolveUser"
    typeResolver: "App\\GraphQL\\NodeResolver@resolveNodeType"
  ) {
  name: String!
}
```

```php
public function resolveNodeType($value): \GraphQL\Type\Definition\Type
```

## @notIn

Filter a column by an array.

```graphql
type Query {
  # this will filter a user's posts that are not in the array of id(s).
  postsByCategory(category_id: [Int] @notIn): [Post] @hasMany
}
```

## @paginate

Return a paginated list. This transforms the schema definition and automatically adds
additional arguments and inbetween types.

```graphql
type Query {
  posts: [Post!]! @paginate
}
```

The `type` of pagination defaults to `paginator`, but may also be set to a Relay
compliant `connection`.

```graphql
type Query {
  posts: [Post!]! @paginate(type: "connection")
}
```

By default, this looks for an Eloquent model in the configured default namespace, with the same
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

class Blog
{
    public function visiblePosts($root, array $args, $context, ResolveInfo $resolveInfo): Builder
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

## @rules

Validate an argument using [Laravel's built-in validation rules](https://laravel.com/docs/5.6/validation#available-validation-rules).
Can be defined on Field Arguments and Input Object Values.

```graphql
type Query {
  users(countryCode: String @rules(apply: ["string", "size:2"])): User
}

input CreatePostInput {
  title: String @rules(apply: ["required"])
  content: String @rules(apply: ["min:50", "max:150"])
}
```

You can customize the error message for a particular argument.

```graphql
@rules(apply: ["max:140"], messages: { max: "Tweets have a limit of 140 characters"})
```

## @scalar

Point Lighthouse to your scalar definition class.
[Learn how to implement your own scalar.](http://webonyx.github.io/graphql-php/type-system/scalar-types/)

Lighthouse looks into your default scalar namespace for a class with the same name.
You do not need to specify the directive in that case.

```graphql
scalar DateTime
```

Pass the class name if it is different from the scalar type.

```graphql
scalar DateTime @scalar(class: "DateTimeScalar")
```

If your class is not in the default namespace, pass a fully qualified class name.

```graphql
scalar DateTime
  @scalar(class: "Nuwave\\Lighthouse\\Schema\\Types\\Scalars\\DateTime")
```

## @search

Creates a full-text search argument.

This directive will make an argument use [Laravel Scout](https://laravel.com/docs/master/scout)
to make a full-text search, what driver you use for Scout is up to you.

The `search` method of the model is called with the value of the argument.

```graphql
type Query {
  posts(search: String @search): [Post!]! @paginate
}
```

Normally the search will be performed using the index specified by the model's `searchableAs` method.
However, in some situation a custom index might be needed, this can be achieved by using the argument `within`.

```graphql
type Query {
  posts(search: String @search(within: "my.index")): [Post!]! @paginate
}
```

## @update

Update an Eloquent model.

```graphql
type Mutation {
  updatePost(id: ID!, content: String): Post @update
}
```

If the name of the Eloquent model does not match the return type of the field, set it with the `model` argument.

```graphql
type Mutation {
  updateAuthor(id: ID!, name: String): Author @update(model: "App\\User")
}
```

## @union

Make sure you read the [basics about Unions](../the-basics/types.md#union) before deciding
to use this directive, you probably don't need it.

You can point Lighthouse to a custom type resolver.
Set the `resolver` argument to a function that returns the implementing Object Type.

```graphql
type User {
  id: ID!
}

type Employee {
  employeeId: ID!
}

union Person @union(resolver: "App\\GraphQL\\UnionResolver@person") =
    User
  | Employee
```

The function receives the value of the parent field as its single argument and must
return an Object Type. You can get the appropriate Object Type from Lighthouse's type registry.

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

## @where

Specify that an argument is used as a [where filter](https://laravel.com/docs/5.7/queries#where-clauses).

You can specify simple operators:

```graphql
type Query {
  postsSearchTitle(title: String! @where(operator: "like")): [Post] @hasMany
}
```

Or use the additional clauses that Laravel provides:

```graphql
type Query {
  postsByYear(created_at: Int! @where(clause: "whereYear")): [Post] @hasMany
}
```

## @whereBetween

Verify that a column's value is between two values.

_Note: You will need to add a `key` to the column to want to query for each date_

```graphql
type Query {
  # this will filter a user's posts between a set of dates.
  postsBetweenDates(
    start_date: String! @whereBetween(key: "created_at")
    end_date: String! @whereBetween(key: "created_at")
  ): [Post] @hasMany
}
```

## @whereNotBetween

Verify that a column's value lies outside of two values.

_Note: You will need to add a `key` to the column to want to query for each date_

```graphql
type Query {
  postsBetweenDates(
    start_date: String! @whereNotBetween(key: "created_at")
    end_date: String! @whereNotBetween(key: "created_at")
  ): [Post] @hasMany
}
```
