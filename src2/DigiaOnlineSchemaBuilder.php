<?php


namespace Nuwave\Lighthouse;


use Closure;
use function Digia\GraphQL\buildSchema;
use function Digia\GraphQL\extendSchema;
use Digia\GraphQL\GraphQL as DigiaGraphQL;
use Digia\GraphQL\Language\Node\ArgumentNode as DigiaArgumentNode;
use Digia\GraphQL\Language\Node\ArgumentsAwareInterface as DigiaArgumentsAware;
use Digia\GraphQL\Language\Node\ASTNodeAwareInterface as DigiaASTNodeAware;
use Digia\GraphQL\Language\Node\DefinitionNodeInterface as DigiaDefinitionNode;
use Digia\GraphQL\Language\Node\DirectiveNode as DigiaDirectiveNode;
use Digia\GraphQL\Language\Node\DirectivesAwareInterface as DigiaDirectiveAware;
use Digia\GraphQL\Language\Node\DocumentNode as DigiaDocumentNode;
use Digia\GraphQL\Language\Node\NodeInterface as DigiaNode;
use function Digia\GraphQL\parse;
use Digia\GraphQL\Schema\Building\SchemaBuilderInterface as DigiaSchemaBuilder;
use Digia\GraphQL\Schema\Extension\SchemaExtenderInterface as DigiaSchemaExtender;
use Digia\GraphQL\Schema\Resolver\ResolverRegistry as DigiaResolverRegistry;
use Digia\GraphQL\Schema\Schema as DigiaSchema;
use Digia\GraphQL\Type\Definition\Argument as DigiaArgument;
use Digia\GraphQL\Type\Definition\EnumType as DigiaEnumType;
use Digia\GraphQL\Type\Definition\EnumValue as DigiaEnumValue;
use Digia\GraphQL\Type\Definition\FieldInterface as DigiaField;
use Digia\GraphQL\Type\Definition\FieldsAwareInterface as DigiaFieldsAware;
use Digia\GraphQL\Type\Definition\InputField as DigiaInputField;
use Digia\GraphQL\Type\Definition\InputObjectType as DigiaInputObjectType;
use Digia\GraphQL\Type\Definition\InterfaceType as DigiaInterfaceType;
use Digia\GraphQL\Type\Definition\ListType as DigiaListType;
use Digia\GraphQL\Type\Definition\NamedTypeInterface as DigiaNamedType;
use Digia\GraphQL\Type\Definition\NonNullType as DigiaNonNullType;
use Digia\GraphQL\Type\Definition\ObjectType as DigiaObjectType;
use Digia\GraphQL\Type\Definition\ScalarType as DigiaScalarType;
use Digia\GraphQL\Type\Definition\TypeInterface as DigiaType;
use GraphQL\Type\Definition\ListOfType as DigiaListOfType;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Types\Argument;
use Nuwave\Lighthouse\Types\EnumType;
use Nuwave\Lighthouse\Types\EnumValueType;
use Nuwave\Lighthouse\Types\Field;
use Nuwave\Lighthouse\Types\InputObjectType;
use Nuwave\Lighthouse\Types\InterfaceType;
use Nuwave\Lighthouse\Types\ListType;
use Nuwave\Lighthouse\Types\NonNullType;
use Nuwave\Lighthouse\Types\ObjectType;
use Nuwave\Lighthouse\Types\Scalar\BooleanType;
use Nuwave\Lighthouse\Types\Scalar\FloatType;
use Nuwave\Lighthouse\Types\Scalar\IDType;
use Nuwave\Lighthouse\Types\Scalar\IntType;
use Nuwave\Lighthouse\Types\Scalar\ScalarType;
use Nuwave\Lighthouse\Types\Scalar\StringType;

class DigiaOnlineSchemaBuilder // implements SchemaBuilder
{

    public function buildFromTypeLanguage(string $schemaDefinition): Schema
    {
        $documentNode = parse($schemaDefinition);

        /** @var DigiaSchema $schema */
        $schema = DigiaGraphQL::make(DigiaSchemaBuilder::class)->build(
            $documentNode,
            new DigiaResolverRegistry([]),
            []
        );

        $extension = collect($documentNode->getDefinitions())->filter(function (DigiaNode $node) {
            return $node->getKind() === "ObjectTypeExtension";
        });
        if($extension->isNotEmpty()) {
            $schema = DigiaGraphQL::make(DigiaSchemaExtender::class)->extend(
                $schema,
                new DigiaDocumentNode(
                    $extension->all(),
                    null
                ),
                new DigiaResolverRegistry([]),
                []
            );
        }

        return new Schema(
            collect($schema->getTypeMap())->filter(function (DigiaNamedType $type) {
                return !Str::startsWith($type->getName(), "__");
            })->map(function (DigiaNamedType $type) {
                return $this->toType($type);
            })
        );
    }

    private function toType(DigiaType $type)
    {
        $fields = function () {
            return collect();
        };

        if($type instanceof DigiaFieldsAware) {
            $fields = function () use ($type) {
                return collect($type->getFields())->map(function (DigiaField $field) {
                    return $this->toField($field);
                });
            };
        }

        $directives = $this->getDirectives($type);

        $name = "";
        if($type instanceof DigiaNamedType) {
            $name = $type->getName();
        }

        if($type instanceof DigiaObjectType) {
            return new ObjectType(
                $name,
                $type->getDescription(),
                $fields,
                $directives
            );
        }
        elseif ($type instanceof DigiaScalarType) {
            return $this->toScalar($type, $name, $fields, $directives);
        }
        elseif ($type instanceof DigiaNonNullType) {
            return new NonNullType(
                $this->toType($type->getOfType())
            );
        }
        elseif ($type instanceof DigiaListType) {
            return new ListType(
                $this->toType($type->getOfType())
            );
        }
        elseif ($type instanceof DigiaEnumType) {
            return new EnumType(
                $name,
                $type->getDescription(),
                collect($type->getValues())->map(function (DigiaEnumValue $value) {
                    return $this->toEnumValue($value);
                })
            );
        }
        elseif ($type instanceof DigiaInterfaceType) {
            return new InterfaceType(
                $name,
                $type->getDescription(),
                $fields
            );
        }
        elseif ($type instanceof DigiaInputObjectType) {
            return new InputObjectType(
                $name,
                $type->getDescription(),
                function () use ($type) {
                    return collect($type->getFields())->map(function (DigiaInputField $field) {
                        return $this->toField($field);
                    });
                }
            );
        }

        dd("unknown type, class: ". get_class($type), $type);
    }

    private function toField(DigiaField $field)
    {
        return new Field(
            $field->getName(),
            $field->getDescription(),
            $this->toType($field->getType()),
            function () use ($field) {
                return collect($field->getArguments())->map(function (DigiaArgument $argument) {
                        return $this->toArgument($argument);
                });
            },
            $this->getDirectives($field)
        );
    }

    public function toArgument(DigiaArgument $argument) : Argument
    {
        return new Argument(
            $argument->getName(),
            $argument->getDescription(),
            $this->toType($argument->getType()),
            null,
            $this->getDirectives($argument)
        );
    }

    private function toEnumValue(DigiaEnumValue $value)
    {
        return new EnumValueType(
            $value->getName(),
            $value->getDescription(),
            $value->getValue()
        );
    }

    /**
     * @param DigiaScalarType $type
     * @param $name
     * @param $fields
     * @param Closure $directives
     * @return ScalarType
     */
    private function toScalar(DigiaScalarType $type, $name, $fields, Closure $directives) : ScalarType
    {
        switch ($type->getName()) {
            case "String":
                return new StringType(
                    $name,
                    $type->getDescription(),
                    $fields
                );
            case "Int":
                return new IntType(
                    $name,
                    $type->getDescription(),
                    $fields
                );
            case "Float":
                return new FloatType(
                    $name,
                    $type->getDescription(),
                    $fields
                );
            case "ID":
                return new IDType(
                    $name,
                    $type->getDescription(),
                    $fields
                );
            case "Boolean":
                return new BooleanType(
                    $name,
                    $type->getDescription(),
                    $fields
                );
        }
        return new ScalarType(
            $name,
            $type->getDescription(),
            $fields,
            $directives
        );
    }

    /**
     * @param DigiaType|DigiaField $type
     * @return Closure
     */
    private function getDirectives($type): Closure
    {
        $directives = function () {
            return collect();
        };
        if ($type instanceof DigiaASTNodeAware && $type->getAstNode() instanceof DigiaDirectiveAware) {
            $directives = function () use ($type) {
                return collect($type->getAstNode()->getDirectives())->map(function (DigiaDirectiveNode $node) {
                        return new Directive(
                            $node->getNameValue(),
                            collect($node->getArguments())->map(function (DigiaArgumentNode $node) {
                                    return new Argument(
                                        $node->getNameValue(),
                                        null,
                                        graphql()->schema()->type("String"),
                                        $node->getValue()->getValue()
                                    );
                                }
                            )
                        );
                    }
                );
            };
        }
        return $directives;
    }
}