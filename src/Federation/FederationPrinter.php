<?php

namespace Nuwave\Lighthouse\Federation;

use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

class FederationPrinter
{
    const FEDERATION_FIELDS = [
        '_service',
        '_entities',
    ];

    const FEDERATION_DIRECTIVES = [
        'key',
        'extends',
        'external',
        'extends',
        'requires',
        'provides',
    ];

    public static function printFederatedSchema(Schema $schema): string
    {
        // TODO decide where to filter directives
//        $directives = (new Collection($directives))->filter(static function ($directive) {
//            return in_array($directive->name->value, static::FEDERATION_DIRECTIVES);
//        });

        $originalQueryType = $schema->getQueryType();
        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => array_filter(
                $originalQueryType->getFields(),
                static function (FieldDefinition $field, string $name): bool {
                    return in_array($name, static::FEDERATION_FIELDS);
                }
            ),
            'interfaces' => $originalQueryType->getInterfaces(),
        ]);

        $newSchema = new Schema([
            'query' => $queryType,
            'mutation' => $schema->getMutationType(),
            'subscription' => $schema->getSubscriptionType(),
            'directives' => array_filter(
                $schema->getDirectives(),
                static function (Directive $directive): bool {
                    return in_array($directive->name, static::FEDERATION_DIRECTIVES);
                }
            ),
        ]);

        return SchemaPrinter::doPrint($newSchema, []);
    }
}
