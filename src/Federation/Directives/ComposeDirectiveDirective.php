<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Federation\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class ComposeDirectiveDirective extends BaseDirective
{
    public const NAME = 'composeDirective';

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'GRAPHQL'
"""
Indicates to composition that all uses of a particular custom type system directive
in the subgraph schema should be preserved in the supergraph schema
(by default, composition omits most directives from the supergraph schema).

```graphql
extend schema
    @link(url: "https://myspecs.dev/myDirective/v1.0", import: ["@myDirective"])
    @composeDirective(name: "@myDirective")
```

https://www.apollographql.com/docs/federation/federated-types/federated-directives#composedirective
"""
directive @composeDirective(name: String!) repeatable on SCHEMA
GRAPHQL;
    }
}
