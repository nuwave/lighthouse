# Arg Resolvers

To understand the concept behind arg resolvers, you should familiarize yourself with
[how field resolvers are composed](https://graphql.org/learn/execution/).

## Motivation

Arg resolvers are an extension of the ideas behind GraphQL field execution,
applied to input arguments. Since GraphQL queries can be used to fetch complex
and deeply nested data from the client, it is natural to assume that such complex
data can also be passed as the input arguments to a query.

GraphQL's execution engine allows you to write small and focused field resolver functions
that only care about returning the data that it is immediately responsible for.
That makes the code much simpler and avoids duplication.

However, a single field resolver still has to take care of all the input arguments that
are passed to it. Handling complex input data in a single function is hard because of their
dynamic nature. The input given by a client might be nested arbitrarily deep
and come in many different variations.

The following example shows an example mutation that is actually composed out of multiple
distinct operations.

```graphql
type Mutation {
  createTask(input: CreateTaskInput): Task!
}

input CreateTaskInput {
  name: String!
  notes: [CreateNoteInput!]
}

input CreateNoteInput {
  content: String!
  link: String
}
```

In a single request, we can pass all data relating to a task,
including related entities such as notes.

```graphql
mutation CreateTaskWithNotes {
  createTask(
    id: 45
    name: "Do something"
    notes: [
      { content: "Foo bar", link: "https://foo.bar" }
      { content: "Awesome note" }
    ]
  ) {
    id
  }
}
```

We might resolve that mutation by writing a resolver function that handles all input at once.

```php
function createTaskWithNotes($root, array $args): \App\Models\Task {
    // Pull and remove notes from the args array
    $notes = \Illuminate\Support\Arr::pull($args, 'notes');

    // Create the new task with the remaining args
    $task = \App\Models\Task::create($args);

    // If the client actually passed notes, create and attach them
    if($notes) {
        foreach($notes as $note) {
            $task->notes()->create($note);
        }
    }

    return $task;
}
```

In this contrived example, the function is still pretty simple. However, separation of concerns
is already violated: A single function is responsible for creating both tasks and notes.

We might want to extend our schema to support more operations in the future, such as updating
a task and creating, updating or deleting notes or other, more deeply nested relations.
Such changes would force us to duplicate code and increase the complexity of our single function.

## Solution

Ideally, we would want to write small and focused functions that each deal with just
a part of the given input arguments. The execution engine should traverse the given
input and take care of calling the appropriate functions with their respective arguments.

```php
function createTask($root, array $args): \App\Models\Task {
    return \App\Models\Task::create($args);
}

function createTaskNotes(\App\Models\Task $root, array $args): void {
    foreach($args as $note) {
        $root->notes()->create($note);
    }
}
```

Lighthouse allows you to attach resolver functions to arguments.
Complex inputs are automatically split into smaller pieces and passed off to the responsible function.

As Lighthouse uses the SDL as the primary building block, arg resolvers are implemented as directives.
Here is how we can define a schema that enables sending a nested mutation as in the example above.

```diff
type Mutation {
- createTask(input: CreateTaskInput): Task!
+ createTask(input: CreateTaskInput): Task! @create
}

input CreateTaskInput {
  name: String!
- notes: [CreateNoteInput!]
+ notes: [CreateNoteInput!] @create
}

input CreateNoteInput {
  content: String!
  link: String
}
```

The [@create](../api-reference/directives.md#create) directive will behave differently, based on the context where it is used.

On the `createTask` field, it will create a `Task` model with the given `name`, save it
to the database and return that instance to Lighthouse.

A simplified, generic implementation of an appropriate field resolver would look something like this:

```php
<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Execution\Arguments\ResolveNested;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CreateDirective extends BaseDirective implements FieldResolver
{
    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        return $fieldValue->setResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Model {
                // Wrap the operation and let Lighthouse take care of splitting the input
                $nestedSave = new ResolveNested(function($model, $args) {
                    $model->fill($args->toArray());
                    $model->save();
                });

                $modelClass = $this->getModelClass();
                /** @var \Illuminate\Database\Eloquent\Model $model */
                $model = new $modelClass;

                return $nestedSave($model, $resolveInfo->argumentSet);
            }
        );
    }
}
```

The arguments that are nested within `notes` will be handled as a nested argument resolver.
For each `CreateNoteInput`, the resolver will be called with the previously created `Task`
and create and attach a related `Note` model.

We can extend our previous implementation of [@create](../api-reference/directives.md#create) by allowing it to be used as an `ArgResolver`:

```php
<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class CreateDirective extends BaseDirective implements FieldResolver, ArgResolver
{
    public function resolveField(FieldValue $fieldValue): FieldValue { ... }

    /**
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  array<\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet>  $argsList
     * @return array<\Illuminate\Database\Eloquent\Model>
     */
    public function __invoke($parent, $argsList): array
    {
        $relationName = $this->getRelationName();

        /** @var \Illuminate\Database\Eloquent\Relations\Relation $relation */
        $relation = $parent->{$relationName}();
        $related = $relation->make();

        return array_map(
            function ($args) use ($related) {
                $related->fill($args->toArray());
                $related->save();
            },
            $argsList
        );
    }
}
```

You may define your own nested arg resolver directives by implementing [`ArgResolver`](../custom-directives/argument-directives.md#argresolver).
