<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Federation\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class LinkDirective extends BaseDirective
{
    public const NAME = 'link';

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'GRAPHQL'
"""
This directive links definitions from an external specification to this schema.
Every Federation 2 subgraph uses the @link directive to import the other federation-specific directives.

```graphql
extend schema @link(url: "https://specs.apollo.dev/federation/v2.3")
```

https://www.apollographql.com/docs/federation/federated-types/federated-directives#the-link-directive
"""
directive @link(url: String!, as: String, for: link__Purpose, import: [link__Import]) repeatable on SCHEMA

"""
An element to import into the document.

```graphql
@link(url: "https://specs.apollo.dev/link/v1.0", import: ["@link", "Purpose"])
```

or an object with name and (optionally as):

```graphql
@link(url: "https://specs.apollo.dev/link/v1.0", import: [{ name: "Purpose", as: "LinkPurpose" }])
```
"""
scalar link__Import

"""
The role of a @linked schema.
"""
enum link__Purpose {
    """
    Provide metadata necessary to securely resolve fields.
    """
    SECURITY

    """
    Provide metadata necessary to correctly resolve fields.
    """
    EXECUTION
}
GRAPHQL;
    }
}
