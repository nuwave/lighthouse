<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Federation\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class TagDirective extends BaseDirective
{
    public const NAME = 'tag';

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'GRAPHQL'
"""
Applies arbitrary string metadata to a schema location. Custom tooling can use this metadata
during any step of the schema delivery flow, including composition, static analysis, and documentation.

```graphql
extend schema
    @link(url: "https://specs.apollo.dev/federation/v2.3", import: ["@tag"])

type Query {
  customer(id: String!): Customer @tag(name: "team-customers")
  employee(id: String!): Employee @tag(name: "team-admin")
}

interface User @tag(name: "team-accounts") {
  id: String!
  name: String!
}

type Customer implements User @tag(name: "team-customers") {
  id: String!
  name: String!
}

type Employee implements User @tag(name: "team-admin") {
  id: String!
  name: String!
  ssn: String!
}
```

https://www.apollographql.com/docs/federation/federated-types/federated-directives#tag
"""
directive @tag(name: String!) repeatable on FIELD_DEFINITION | INTERFACE | OBJECT | UNION | ARGUMENT_DEFINITION | SCALAR | ENUM | ENUM_VALUE | INPUT_OBJECT | INPUT_FIELD_DEFINITION
GRAPHQL;
    }
}
