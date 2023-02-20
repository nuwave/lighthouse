# Sanitization

When dealing with user input, you need to make sure the given data is valid.
While [validation](validation) is a great first line of defense, there are cases where
it is most practical to modify the given input to ensure it is valid or safe to use.

## Single arguments

A great way to deal with single values is to use an [`ArgTransformerDirective`](../custom-directives/argument-directives.md#argtransformerdirective).
Lighthouse offers a few built-in options, but it is also really easy to build your own.

Here is how you can remove whitespace of a given input string by using
the built-in [@trim](../api-reference/directives.md#trim) directive:

```graphql
type Mutation {
  createPost(title: String @trim): Post
}
```

## Complex arguments

When you need to look at multiple input fields in order to run sanitization, you can use
a [`FieldMiddlewareDirective`](../custom-directives/field-directives.md#fieldmiddleware)
to transform the given inputs before passing them along to the final resolver.
