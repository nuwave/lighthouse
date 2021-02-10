<?php

namespace Nuwave\Lighthouse\Federation;

use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use Illuminate\Support\Arr;

class FederationPrinter
{
    const FEDERATION_TYPES = [
        '_Any',
        '_Entity',
        '_FieldSet',
        '_Service',
    ];

    const FEDERATION_FIELDS = [
        '_service',
        '_entities',
    ];

    const FEDERATION_DIRECTIVES = [
        'extends',
        'external',
        'key',
        'provides',
        'requires',
    ];

    public static function print(Schema $schema): string
    {
        // TODO decide where to filter directives
//        $directives = (new Collection($directives))->filter(static function ($directive) {
//            return in_array($directive->name->value, static::FEDERATION_DIRECTIVES);
//        });
        $config = SchemaConfig::create();

        $types = $schema->getTypeMap();
        foreach (self::FEDERATION_TYPES as $type) {
            unset($types[$type]);
        }

        $originalQueryType = Arr::pull($types, 'Query');
        $config->setQuery(new ObjectType([
            'name' => 'Query',
            'fields' => array_filter(
                $originalQueryType->getFields(),
                static function (FieldDefinition $field): bool {
                    return ! in_array($field->name, static::FEDERATION_FIELDS);
                }
            ),
            'interfaces' => $originalQueryType->getInterfaces(),
        ]));

        $config->setMutation(Arr::pull($types, 'Mutation'));

        $config->setSubscription(Arr::pull($types, 'Subscription'));

        $config->setTypes($types);

        $config->setDirectives(array_filter(
            $schema->getDirectives(),
            static function (Directive $directive): bool {
                return ! in_array($directive->name, static::FEDERATION_DIRECTIVES);
            }
        ));

        return SchemaPrinter::doPrint(new Schema($config), []);
    }
}
