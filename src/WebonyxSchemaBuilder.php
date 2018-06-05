<?php


namespace Nuwave\Lighthouse;


use GraphQL\Type\Definition\EnumValueDefinition as WebonyxEnumValueDefinition;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\InputObjectField;
use GraphQL\Type\Definition\InputObjectType as WebonyxInputObjectType;
use GraphQL\Type\Definition\InterfaceType as WebonyxInterfaceType;
use GraphQL\Type\Definition\ListOfType as WebonyxListOfType;
use GraphQL\Type\Definition\NonNull as WebonyxNonNullType;
use GraphQL\Type\Definition\BooleanType as WebonyxBooleanType;
use GraphQL\Type\Definition\EnumType as WebonyxEnumType;
use GraphQL\Type\Definition\FieldDefinition as WebonyxFieldDefinition;
use GraphQL\Type\Definition\FloatType as WebonyxFloatType;
use GraphQL\Type\Definition\IDType as WebonyxIDType;
use GraphQL\Type\Definition\IntType as WebonyxIntType;
use GraphQL\Type\Definition\ObjectType as WebonyxObjectType;
use GraphQL\Type\Definition\ScalarType as WebonyxScalarType;
use GraphQL\Type\Definition\StringType as WebonyxStringType;
use GraphQL\Type\Definition\Type as WebonyxType;
use GraphQL\Utils\BuildSchema;
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
use Nuwave\Lighthouse\Types\Type;

class WebonyxSchemaBuilder// implements SchemaBuilder
{

    public function buildFromTypeLanguage(string $schema) : Schema
    {
        $schema = BuildSchema::build($schema);


        return new Schema(
            collect($schema->getTypeMap())->filter(function (WebonyxType $type) {
                return !Str::startsWith($type->name, "__");
            })->map(function (WebonyxType $type) {
                return $this->toType($type);
            })
        );
    }

    public function toType(WebonyxType $type) : Type
    {
        $fields = function () {
            return collect();
        };

        if(
            $type instanceof WebonyxObjectType ||
            $type instanceof WebonyxInterfaceType ||
            $type instanceof WebonyxInputObjectType
        ) {
            $fields = function () use ($type) {
                return collect($type->getFields())->map(function ($field) {
                    return $this->toField($field);
                });
            };
        }

        if($type instanceof WebonyxObjectType) {
            return new ObjectType(
                $type->name,
                $type->description,
                $fields
            );
        }
        elseif ($type instanceof WebonyxIDType) {
            return new IDType(
                $type->name,
                $type->description,
                $fields
            );
        }
        elseif ($type instanceof WebonyxStringType) {
            return new StringType(
                $type->name,
                $type->description,
                $fields
            );
        }
        elseif ($type instanceof WebonyxFloatType) {
            return new FloatType(
                $type->name,
                $type->description,
                $fields
            );
        }
        elseif ($type instanceof WebonyxIntType) {
            return new IntType(
                $type->name,
                $type->description,
                $fields
            );
        }
        elseif ($type instanceof WebonyxBooleanType) {
            return new BooleanType(
                $type->name,
                $type->description,
                $fields
            );
        }
        elseif ($type instanceof WebonyxScalarType) {
            return new ScalarType(
                $type->name,
                $type->description,
                $fields
            );
        }
        elseif ($type instanceof WebonyxEnumType) {
            return new EnumType(
                $type->name,
                $type->description,
                collect($type->getValues())->map(function (WebonyxEnumValueDefinition $definition) {
                    return $this->toEnumValue($definition);
                })
            );
        }
        elseif ($type instanceof WebonyxNonNullType) {
            return new NonNullType(
                $this->toType($type->getWrappedType(false))
            );
        }
        elseif ($type instanceof WebonyxListOfType) {
            return new ListType(
                $this->toType($type->getWrappedType(false))
            );
        }
        elseif ($type instanceof WebonyxInterfaceType) {
            return new InterfaceType(
                $type->name,
                $type->description,
                $fields
            );
        }
        elseif ($type instanceof WebonyxInputObjectType) {
            return new InputObjectType(
                $type->name,
                $type->description,
                $fields
            );
        }

        dd("unknown type {$type}, class: ". get_class($type), $type);
    }

    public function toField($fieldDefinition) : Field
    {
        return new Field(
            $fieldDefinition->name,
            $fieldDefinition->description ?? "",
            $this->toType($fieldDefinition->getType()),
            function () use ($fieldDefinition) {
                return collect($fieldDefinition->args)->map(function (FieldArgument $argument) {
                   return $this->toArgument($argument);
                });
            }
        );
    }

    public function toArgument(FieldArgument $argument) : Argument
    {
        return new Argument(
            $argument->name,
            $argument->description,
            $this->toType($argument->getType())
        );
    }

    public function toEnumValue(WebonyxEnumValueDefinition $enumValue) : EnumValueType
    {
        return new EnumValueType(
            $enumValue->name,
            $enumValue->description,
            $enumValue->value
        );
    }
}