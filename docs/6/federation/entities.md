# Entities

A core component of a federation capable GraphQL service is the `_entities` field.
For a given `__typename` in the given `$representations`, Lighthouse will look for
a [reference resolver](reference-resolvers.md) to return the full `_Entity`.

## Extends

You have to use `@extends` in place of `extend type` to annotate type references.
This is because Lighthouse merges type extensions before the final schema is produced,
thus they would not be preserved to appear in the federation schema SDL.
