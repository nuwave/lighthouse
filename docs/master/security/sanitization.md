# Sanitization

When dealing with user input, you need to make sure the given data is valid.
[Validation](validation) is a great first line of defense.
In some cases, it is most practical to modify the given input to ensure it is valid or safe to use.

## Single arguments

A great way to deal with single values is to use an [`ArgTransformerDirective`](../custom-directives/field-argument-directives#argtransformerdirective).
Lighthouse offers a few built-in options, and you can build your own.

Here is how you can remove whitespace of a given input string by using the built-in [@trim](../api-reference/directives.md#trim) directive:

```graphql
type Mutation {
  createPost(title: String @trim): Post
}
```

## Complex arguments

When sanitization needs to look at multiple input fields, use a [`FieldMiddlewareDirective`](../custom-directives/field-directives.md#fieldmiddleware).
It can transform the given inputs before passing them along to the final resolver.
