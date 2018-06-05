<?php


namespace Nuwave\Lighthouse;


use function Digia\GraphQL\buildSchema;
use Digia\GraphQL\Execution\ResolveInfo;
use Digia\GraphQL\Schema\Schema as DigiaSchema;
use function Digia\GraphQL\Type\Boolean;
use function Digia\GraphQL\Type\Float;
use function Digia\GraphQL\Type\ID;
use function Digia\GraphQL\Type\Int;
use function Digia\GraphQL\Type\newInputObjectType;
use function Digia\GraphQL\Type\newList;
use function Digia\GraphQL\Type\newNonNull;
use function Digia\GraphQL\Type\newObjectType;
use function Digia\GraphQL\Type\newScalarType;
use function Digia\GraphQL\Type\newSchema;
use function Digia\GraphQL\graphql as execute;
use function Digia\GraphQL\Type\String;
use Exception;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Types\Argument;
use Nuwave\Lighthouse\Types\Field;
use Nuwave\Lighthouse\Types\InputObjectType;
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

class DigiaOnlineExecutor// implements Executor
{
    public $digiaTypes;

    public function __construct()
    {
        $this->digiaTypes = collect();
    }


    public function execute(Schema $schema, string $query)
    {
        return execute(
            $this->toDigiaSchema($schema),
            $query
        );
    }

    public function toDigiaSchema(Schema $schema) : DigiaSchema
    {
        $types = $schema->types()->mapWithKeys(function (Type $type, $key) {
            return [
                Str::lower($key) => $this->toDigiaType($type)
            ];
        });
        return newSchema($types->all());
    }

    public function toDigiaType(Type $type)
    {
        if($type instanceof NonNullType || $type instanceof ListType || $type instanceof ScalarType) {
            return $this->convertToDigiaType($type);
        }

        if($this->digiaTypes->has($type->name())) {
            return $this->digiaTypes->get($type->name());
        }

        $this->digiaTypes->put(
            $type->name(),
            $type = $this->convertToDigiaType($type)
        );

        return $type;
    }

    public function convertToDigiaType(Type $type)
    {
        $default = [
            'name' => $type->name(),
            'description' => $type->description(),
            'fields' => function () use ($type) {
                return $type->fields()->map(function (Field $field) {
                    return [
                        'type' => $this->toDigiaType($field->type()),

                        /*
                        'type' => newList(newObjectType([
                            'name'   => 'User',
                            'fields' => [
                                'name' => ['type' => newNonNull(String())],
                            ]
                        ])),
                        */

                        'description' => $field->description(),
                        'args' => $field->arguments()->mapWithKeys(function (Argument $argument) {
                            return [
                                $argument->name() => [
                                    'name' => $argument->name(),
                                    'description' => $argument->description(),
                                    'type' => $this->toDigiaType($argument->type()),
                                    'defaultValue' => $argument->defaultValue(),
                                ]
                            ];
                        })->all(),
                        'resolve' => function($response, $param1, $param2, ResolveInfo $resolveInfo) use ($field) {
                            $result = null;

                            return ($field->resolver($result))();
                        }
                    ];
                })->all();
            }
        ];

        if($type instanceof ObjectType) {
            return newObjectType($default);
        }
        elseif ($type instanceof ScalarType) {
            return $this->toScalarType($type);
        }
        elseif ($type instanceof NonNullType) {
            return newNonNull($this->toDigiaType($type->getWrappedType()));
        }
        elseif ($type instanceof ListType) {
            return newList($this->toDigiaType($type->getWrappedType()));
        }
        elseif ($type instanceof InputObjectType) {
            return newInputObjectType($default);
        }

        throw new Exception("cannot convert ".get_class($type). " to digia type");
    }

    /**
     * @param Type $type
     * @return \Digia\GraphQL\Type\Definition\ScalarType
     * @throws \Digia\GraphQL\Error\InvariantException
     */
    private function toScalarType(Type $type): \Digia\GraphQL\Type\Definition\ScalarType
    {
        if ($type instanceof StringType) {
            return string();
        } else if ($type instanceof BooleanType) {
            return boolean();
        } else if ($type instanceof FloatType) {
            return float();
        } else if ($type instanceof IntType) {
            return int();
        } else if ($type instanceof IDType) {
            return id();
        }
        return newScalarType(
            [
                'name'        => $type->name(),
                'description' => $type->description(),
                'serialize'   => function () {
                    return "";
                }
            ]
        );
    }
}