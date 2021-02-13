<?php

namespace Nuwave\Lighthouse\Federation;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumValueDefinition;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InputObjectField;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use GraphQL\Utils\Utils;
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

        $printDirectives = static function ($definition): string {
            /** @var Type|EnumValueDefinition|FieldArgument|FieldDefinition|InputObjectField $definition */
            $astNode = $definition->astNode;
            if ($astNode === null) {
                return '';
            }

            if ($astNode instanceof ObjectTypeDefinitionNode) {
                return SchemaPrinter::printDirectives(
                    Utils::filter(
                        $astNode->directives,
                        static function (DirectiveNode $directive): bool {
                            $name = $directive->name->value;
                            return $name === 'key'
                                || $name === 'extends';
                        }
                    )
                );
            } elseif ($astNode instanceof FieldDefinitionNode) {
                return SchemaPrinter::printDirectives(
                    Utils::filter(
                        $astNode->directives,
                        static function (DirectiveNode $directive): bool {
                            $name = $directive->name->value;
                            return $name === 'provides'
                                || $name === 'requires'
                                || $name === 'external';
                        }
                    )
                );
            }

            return '';
        };

        return SchemaPrinter::doPrint(
            new Schema($config),
            // @phpstan-ignore-next-line We extended the SchemaPrinter to allow for this option
            ['printDirectives' => $printDirectives]
        );
    }

}
