<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Federation;

use GraphQL\Type\Definition\Argument;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\EnumValueDefinition;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InputObjectField;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Federation\Directives\AuthenticatedDirective;
use Nuwave\Lighthouse\Federation\Directives\CacheTagDirective;
use Nuwave\Lighthouse\Federation\Directives\ComposeDirectiveDirective;
use Nuwave\Lighthouse\Federation\Directives\ContextDirective;
use Nuwave\Lighthouse\Federation\Directives\CostDirective;
use Nuwave\Lighthouse\Federation\Directives\ExtendsDirective;
use Nuwave\Lighthouse\Federation\Directives\ExternalDirective;
use Nuwave\Lighthouse\Federation\Directives\FromContextDirective;
use Nuwave\Lighthouse\Federation\Directives\InaccessibleDirective;
use Nuwave\Lighthouse\Federation\Directives\InterfaceObjectDirective;
use Nuwave\Lighthouse\Federation\Directives\KeyDirective;
use Nuwave\Lighthouse\Federation\Directives\LinkDirective;
use Nuwave\Lighthouse\Federation\Directives\ListSizeDirective;
use Nuwave\Lighthouse\Federation\Directives\OverrideDirective;
use Nuwave\Lighthouse\Federation\Directives\PolicyDirective;
use Nuwave\Lighthouse\Federation\Directives\ProvidesDirective;
use Nuwave\Lighthouse\Federation\Directives\RequiresDirective;
use Nuwave\Lighthouse\Federation\Directives\RequiresScopesDirective;
use Nuwave\Lighthouse\Federation\Directives\ShareableDirective;
use Nuwave\Lighthouse\Federation\Directives\TagDirective;
use Nuwave\Lighthouse\Schema\RootType;

class FederationPrinter
{
    public const FEDERATION_TYPES = [
        '_Any',
        '_Entity',
        '_FieldSet',
        '_Service',
    ];

    public const FEDERATION_FIELDS = [
        '_service',
        '_entities',
    ];

    public const FEDERATION_DIRECTIVES = [
        ExtendsDirective::NAME,
        ExternalDirective::NAME,
        KeyDirective::NAME,
        ProvidesDirective::NAME,
        RequiresDirective::NAME,
        ShareableDirective::NAME,
        InaccessibleDirective::NAME,
        InterfaceObjectDirective::NAME,
        OverrideDirective::NAME,
        TagDirective::NAME,
        LinkDirective::NAME,
        AuthenticatedDirective::NAME,
        RequiresScopesDirective::NAME,
        PolicyDirective::NAME,
        ContextDirective::NAME,
        FromContextDirective::NAME,
        CostDirective::NAME,
        ListSizeDirective::NAME,
        CacheTagDirective::NAME,
        ComposeDirectiveDirective::NAME,
    ];

    public static function print(Schema $schema): string
    {
        $config = SchemaConfig::create();

        $types = $schema->getTypeMap();
        foreach (self::FEDERATION_TYPES as $type) {
            unset($types[$type]);
        }

        $originalQueryType = Arr::pull($types, RootType::QUERY);
        assert($originalQueryType instanceof ObjectType);

        $queryFieldsWithoutFederation = array_filter(
            $originalQueryType->getFields(),
            static fn (FieldDefinition $field): bool => ! in_array($field->name, static::FEDERATION_FIELDS),
        );
        $newQueryType = $queryFieldsWithoutFederation !== []
            ? new ObjectType([
                'name' => RootType::QUERY,
                'description' => $originalQueryType->description,
                'astNode' => $originalQueryType->astNode,
                'fields' => $queryFieldsWithoutFederation,
                'interfaces' => $originalQueryType->getInterfaces(),
            ])
            : null;
        $config->setQuery($newQueryType);

        $config->setMutation(Arr::pull($types, RootType::MUTATION));

        $config->setSubscription(Arr::pull($types, RootType::SUBSCRIPTION));

        $config->setTypes($types);

        $federationDirectives = array_merge(
            static::FEDERATION_DIRECTIVES,
            FederationHelper::directivesToCompose($schema),
        );

        $config->setDirectives(array_filter(
            $schema->getDirectives(),
            static fn (Directive $directive): bool => ! in_array($directive->name, $federationDirectives),
        ));

        $config->setExtensionASTNodes($schema->extensionASTNodes);

        $printDirectives = static function ($definition) use ($federationDirectives): string {
            assert(
                $definition instanceof EnumType
                || $definition instanceof InputObjectType
                || $definition instanceof InterfaceType
                || $definition instanceof ObjectType
                || $definition instanceof ScalarType
                || $definition instanceof UnionType
                || $definition instanceof EnumValueDefinition
                || $definition instanceof Argument
                || $definition instanceof FieldDefinition
                || $definition instanceof InputObjectField,
            );

            $astNode = $definition->astNode;

            $directivesToPrint = [];
            foreach ($astNode->directives ?? [] as $directive) {
                if (in_array($directive->name->value, $federationDirectives)) {
                    $directivesToPrint[] = $directive;
                }
            }

            return SchemaPrinter::printDirectives($directivesToPrint);
        };

        return SchemaPrinter::doPrint(
            new Schema($config),
            ['printDirectives' => $printDirectives],
        );
    }
}
