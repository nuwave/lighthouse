<?php

namespace Nuwave\Lighthouse\Federation\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class ProvidesDirective extends BaseDirective
{
    public const NAME = 'provides';

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'GRAPHQL'
"""
The `@provides` directive is used to annotate the expected returned fieldset from a field
on a base type that is  guaranteed to be selectable by the gateway. Given the following example:

```graphql
type Review @key(fields: "id") {
  product: Product @provides(fields: "name")
}

extend type Product @key(fields: "upc") {
  upc: String @external
  name: String @external
}
```

When fetching `Review.product` from the Reviews service, it is possible to request the `name`
with the expectation that the Reviews service can provide it when going from review to product.
`Product.name` is an external field on an external type which is why the local type extension
of `Product` and annotation of `name` is required.

https://www.apollographql.com/docs/federation/federation-spec/#provides
"""
directive @provides(
    """
    The fields this service can provide from the returned type of this field.
    """
    fields: _FieldSet!
) on FIELD_DEFINITION
GRAPHQL;
    }
}
