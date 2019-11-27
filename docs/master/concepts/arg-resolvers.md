# Arg Resolvers

To understand the concept behind arg resolvers, you should familiarize yourself with
[how field resolvers are composed](field-resolvers.md).

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
distinct operations. In a single request, we can pass all data relating to a task,
including related entities such as notes.

```graphql
mutation CreateTaskWithNotes {
  createTask(
    id: 45
    name: "Do something"
    notes: [
      {
        content: "Foo bar",
        link: "http://foo.bar"
      },
      {
        content: "Awesome note"
      }
    ]
  ) {
    id
  }
}
```

Ideally, we would want to write small and focused functions that each deal with just
a part of the given input arguments. The execution engine should traverse the given
input and take care of calling the appropriate functions with their respective arguments.

## Solution

Lighthouse allows you to attach resolver functions to arguments.
Complex inputs are automatically split into smaller pieces and passed off to the responsible function.

As Lighthouse uses the SDL as the primary building block, arg resolvers are implemented as a
kind of directive: [`ArgResolver`](../custom-directives/argument-directives.md#argresolver).
Here is how we can define a schema that enables sending a nested mutation as in the example above.

```graphql
type Mutation {
  createTask(input: CreateTaskInput): Task! @create
}

input CreateTaskInput {
  name: String!
  notes: [CreateNoteInput!] @create
}

input CreateNoteInput {
  content: String!
  link: String
}
```

// TODO elaborate on how the input is split and in what order the resolver are called, with what
