<?php

namespace Nuwave\Lighthouse\Federation\Resolvers;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Utils\SchemaPrinter;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class Service
{
    // TODO find a better place for those constants
    const FEDERATION_QUERY_FIELDS = ['_entities', '_service'];
    const FEDERATION_DIRECTIVES = ['key', 'extends', 'external', 'extends', 'requires', 'provides'];

    /**
     * @return array
     */
    public function resolveSdl($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $schema = $resolveInfo->schema;

        $queryFields = [];
        foreach ($schema->getQueryType()->getFields() as $field) {
            if (! in_array($field->name, static::FEDERATION_QUERY_FIELDS)) {
                $queryFields[] = $field;
            }
        }

        $directives = [];
        foreach ($schema->getDirectives() as $directive) {
            if (! in_array($directive->name, static::FEDERATION_DIRECTIVES)) {
                $directives[] = $directive;
            }
        }

        $types = [];
        foreach ($schema->getTypeMap() as $name => $type) {
            if ($type instanceof ObjectType) {
                $types[] = $type;
            }
        }

        // $schemaConfig = SchemaConfig::create();
        // $schemaConfig->setQuery($queryFields);
        /*$newSchema = new Schema([
            'query'        => $schemaConfig,
            'mutation'     => $schema->getMutationType(),
            'subscription' => $schema->getSubscriptionType(),
            'types'        => $types,
            'directives'   => $directives,
            'typeLoader'   => $schema->getConfig()->getTypeLoader(),
        ]);*/

        // TODO the new schema should be printed including the inline (federation) directives required for federation to work. We may need to create our own schema printer for this.
        return ['sdl' => SchemaPrinter::doPrint($schema)];
    }
}
