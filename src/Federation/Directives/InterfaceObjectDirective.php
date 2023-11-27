<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Federation\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

class InterfaceObjectDirective extends BaseDirective
{
    public const NAME = 'interfaceObject';

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'GRAPHQL'
"""
Indicates that an object definition serves as an abstraction of another subgraph's entity interface.
This abstraction enables a subgraph to automatically contribute fields to all entities that implement a particular entity interface.

During composition, the fields of every @interfaceObject are added both to their corresponding interface definition
and to all entity types that implement that interface.

https://www.apollographql.com/docs/federation/federated-types/federated-directives#interfaceobject
"""
directive @interfaceObject on OBJECT
GRAPHQL;
    }
}
