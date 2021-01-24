# Directives

## @auth

Return the currently authenticated user as the result of a query.

```graphql
type Query {
  me: User @auth
}
```

If you need to use a guard besides the default to resolve the authenticated user,
you can pass the guard name as the `guard` argument

```graphql
type Query {
  me: User @auth(guard: "api")
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
in the default model namespace `App`. [You can change this configuration](../getting-started/configuration.md).

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

The directive accepts an optional `relation` argument if your relationship method
has a different name than the field.

```graphql
type User {
  jobs: [Role!]! @belongsToMany(relation: "roles")
}
```

## @bcrypt

**_Directive Type_**: [ArgTransformerDirective](../guides/custom-directives.md#argtransformerdirective).

Run the `bcrypt` function on the argument it is defined on.

```graphql
type Mutation {
  createUser(name: String, password: String @bcrypt): User
}
```

## @broadcast

Broadcast the results of a mutation to subscribed clients.
[Read more about subscriptions](../extensions/subscriptions.md)

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

Set the name of the policy to check against.

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

If you pass an `id` argument it will look for an instance of the expected model instance.

```graphql
type Query {
  post(id: ID @eq): Post @can(ability: "view")
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

Place on fields to perform analysis to calculate a query complexity score before execution. [Read More](https://webonyx.github.io/graphql-php/security/#query-complexity-analysis)

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

If you are using a single input object as an argument, you must tell Lighthouse
to `flatten` it before applying it to the resolver.

```graphql
type Mutation {
  createPost(input: CreatePostInput!): Post @create(flatten: true)
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

If the name of the Eloquent model does not match the return type of the field,
or is located in a non-default namespace, set it with the `model` argument.

```graphql
type Mutation {
  deletePost(id: ID!): Post @delete(model: "Bar\\Baz\\MyPost")
}
```

## @deprecated

You can mark fields as deprecated by adding the `@deprecated` directive and providing a
`reason` (required). Deprecated fields are not included in introspection queries unless
requested and they can still be queried by clients.

```graphql
type Query {
  users: [User] @deprecated(reason: "Use the `allUsers` field")
  allUsers: [User]
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
    @field(resolver: "App\\GraphQL\\Mutations\\PostMutator@create")
}
```

If your field is defined on the root types `Query` or `Mutation`, you can take advantage
of the default namespaces that are defined in the [configuration](../getting-started/configuration.md). The following
will look for a class in `App\GraphQL\Queries` by default.

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

Other then [@find](#find), this will not throw an error if more then one items are in the collection.

If your model does not sit in the default namespace, you can overwrite it.

```graphql
type Query {
  userByFirstName(first_name: String! @eq): User
    @first(model: "App\\Authentication\\User")
}
```

## @enum

Map the underlying value to an enum key. When dealing with the Enum type in your code,
you will receive the defined value instead of the string key.

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

## @globalId

Converts an ID to a global ID.

```graphql
type User {
  id: ID! @globalId
  name: String
}
```

Instead of the original ID, the `id` field will now return a base64-encoded String
that globally identifies the User and can be used for querying the `node` endpoint.

## @group

Apply common settings to all fields of an Object Type.

Set a common namespace for the [@field](#field) and the [@complexity](#complexity) directives
that are defined on the fields of the defined type.

```graphql
extend type Query @group(namespace: "App\\Authentication") {
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

Filter a column by an array using a `whereIn` clause.

```graphql
type Query {
  posts(includeIds: [Int!] @in(key: "id")): [Post!]! @paginate
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

## @middleware

Run Laravel middleware for a specific field. This can be handy to reuse existing
middleware.

```graphql
type Query {
  users: [User!]! @middleware(checks: ["auth:api"]) @all
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

Enable fetching an Eloquent model by its global id, may be used for Relay.
Behind the scenes, Lighthouse will decode the global id sent from the client to find the model by it's primary id in the database.

```graphql
type User @model {
  id: ID! @globalId
}
```

## @neq

Place a not equals operator `!=` on an Eloquent query.

```graphql
type User {
  posts(excludeCategory: String @neq(key: "category")): [Post!]! @hasMany
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

Filter a column by an array using a `whereNotIn` clause.

```graphql
type Query {
  posts(excludeIds: [Int!] @notIn(key: "id")): [Post!]! @paginate
}
```

## @orderBy

Sort a result list by one or more given fields.

```graphql
type Query {
  posts(orderBy: [OrderByClause!] @orderBy): [Post!]!
}
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

## @paginate

Transform a field so it returns a paginated list.

```graphql
type Query {
  posts: [Post!]! @paginate
}
```

The schema definition is automatically transformed to this:

```graphql
type Query {
  posts(count: Int!, page: Int): PostPaginator
}

type PostPaginator {
  data: [Post!]!
  paginatorInfo: PaginatorInfo!
}
```

And can be queried like this:

```graphql
{
  posts(count: 10) {
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

## @rules

Validate an argument using [Laravel's built-in validation rules](https://laravel.com/docs/5.6/validation#available-validation-rules).

```graphql
type Query {
  users(countryCode: String @rules(apply: ["string", "size:2"])): [User!]!
    @paginate
}
```

Rules can also be defined on Input Object Values.

```graphql
input CreatePostInput {
  title: String @rules(apply: ["required"])
  content: String @rules(apply: ["min:50", "max:150"])
}
```

You can customize the error message for a particular argument.

```graphql
@rules(apply: ["max:140"], messages: { max: "Tweets have a limit of 140 characters"})
```

## @rulesForArray

Run validation on an array itself, using [Laravel's built-in validation rules](https://laravel.com/docs/5.6/validation#available-validation-rules).

```graphql
type Mutation {
  saveIcecream(
    flavors: [IcecreamFlavor!]! @rulesForArray(apply: ["min:3"])
  ): Icecream
}
```

You can also combine this with [@rules](../api-reference/directives.md#rules) to validate
both the size and the contents of an argument array.
For example, you might require a list of at least 3 valid emails to be passed.

```graphql
type Mutation {
  attachEmails(
    email: [String!]! @rules(apply: ["email"]) @rulesForArray(apply: ["min:3"])
  ): File
}
```

## @scalar

Point Lighthouse to your scalar definition class.
[Learn how to implement your own scalar.](https://webonyx.github.io/graphql-php/type-system/scalar-types/)

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

## @subscription

Declare a class to handle the broadcasting of a subscription to clients.

If you follow the default naming conventions for [defining subscription fields](../extensions/subscriptions.md#defining-fields)
you do not need this directive. It is only useful if you need to override the default namespace.

```graphql
type Subscription {
  postUpdated(author: ID!): Post
    @subscription(class: "App\\GraphQL\\Blog\\PostUpdatedSubscription")
}
```

## @trim

**_Directive Type_**: [ArgTransformerDirective](../guides/custom-directives.md#argtransformerdirective).

Run the `trim` function on the argument it is defined on.

```graphql
type Mutation {
  createUser(name: String @trim): User
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

## @update

Update an Eloquent model.

```graphql
type Mutation {
  updatePost(id: ID!, content: String): Post @update
}
```

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

Specify that an argument is used as a [where filter](https://laravel.com/docs/5.7/queries#where-clauses).

You can specify simple operators:

```graphql
type Query {
  postsSearchTitle(title: String! @where(operator: "like")): [Post!]! @hasMany
}
```

Or use the additional clauses that Laravel provides:

```graphql
type Query {
  postsByYear(created_at: Int! @where(clause: "whereYear")): [Post!]! @hasMany
}
```

## @whereBetween

Verify that a column's value is between two values.

_Note: You will need to add a `key` to the column to want to query for each date_

```graphql
type Query {
  posts(
    createdAfter: Date! @whereBetween(key: "created_at")
    createdBefore: String! @whereBetween(key: "created_at")
  ): [Post!]! @all
}
```

## @whereNotBetween

Verify that a column's value lies outside of two values.

_Note: You will need to add a `key` to the column to want to query for each date_

```graphql
type Query {
  users(
    bornBefore: Date! @whereNotBetween(key: "created_at")
    bornAfter: Date! @whereNotBetween(key: "created_at")
  ): [User!]! @all
}
```

## @with

Eager-load an Eloquent relation.

```graphql
type User {
  taskSummary: String! @with(relation: "tasks") @method(name: "getTaskSummary")
}
```

This can be a useful optimization for fields that are not returned directly
but rather used for resolving other fields.

If you just want to return the relation itself as-is,
look into [handling Eloquent relationships](../guides/relationships.md).
