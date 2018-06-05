<?php


namespace Nuwave\Lighthouse\Schema\Directives\Args;

use Closure;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Type\Definition\InputObjectField;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddleware;
use Nuwave\Lighthouse\Support\Contracts\NodeNodeMiddleware;
use Nuwave\Lighthouse\Support\Traits\CreatesPaginators;
use Nuwave\Lighthouse\Support\Traits\HandlesQueryFilter;

class FilterDirective implements NodeNodeMiddleware, ArgMiddleware
{
    use CreatesPaginators, HandlesQueryFilter;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return "filter";
    }

    /**
     * Handle node value.
     *
     * @param NodeValue $value
     *
     * @param Closure $next
     * @return NodeValue
     */
    public function handle(NodeValue $value, Closure $next) : NodeValue
    {
        $type = $value->getType()->name();

        $fields = function () use ($value, $type) {
            $originalType = $type;
            $fields = collect($value->getNodeFields())->map(function(FieldDefinitionNode $node) use ($originalType) {
                $type = $node->type;
                while (property_exists($type, 'type')){
                    $type = $type->type;
                }

                $type = $type->name->value;
                return $this->convertType($node->name->value, $type, $originalType);
            });

            $fields->push($this->andType($type));
            $fields->push($this->orType($type));
            return $fields->flattenKeepKeys(1)->all();
        };

        $filterType = new InputObjectType([
            'name' => "{$type}Filter",
            'fields' => $fields
        ]);

        schema()->type($filterType);

        return $next($value);
    }

    /**
     * Resolve the field directive.
     *
     * @param ArgumentValue $argument
     *
     * @param Closure $next
     * @return ArgumentValue
     */
    public function handleArgument(ArgumentValue $argument, Closure $next)
    {
        $argument = $this->injectFilter($argument, [
            'resolve' => function ($query, $key, array $args) use ($argument) {
                //dd($this->resolveArgument($argument, $query, $key, $args)->toSql());
                return $this->resolve($argument, $query, $key, $args);
            },
        ]);


        return $next($argument);
    }

    /**
     * Manipulates the query based on the argument or type.
     *
     * @param ArgumentValue|Type $type
     * @param Builder$query
     * @param $key
     * @param array $args
     * @return Builder
     */
    public function resolve($type, $query, $key, array $args)
    {
        if($type instanceof ArgumentValue) {
            $type = $type->getType();
        }
        $args = array_get($args, $key);
        collect($type->getFields())->each(function(InputObjectField $field) use (&$query, $args, $key, $type) {
            if(Arr::has($args, $field->name)) {
                $query = ($field->resolve)($query, Arr::get($args, $field->name), $type);
            }
        });
        return $query;
    }


    public function andType(string $type) {
        return $this->nestedType($type, "AND");
    }

    public function orType(string $type) {
        return $this->nestedType($type, "OR");
    }

    /**
     * Generates a nested type object.
     *
     * It wraps the query so we can easily use `AND` or `OR`
     *
     * @param string $type
     * @param string $boolean should be `AND` or `OR`
     * @return array
     */
    public function nestedType(string $type, string $boolean)
    {
        return [
            $boolean => [
                'type' => Type::listOf(Type::nonNull(schema()->instance("{$type}Filter"))),
                'description' => "Logical OR on all given filters.",
                'resolve' => function($query, $values, $argument) use ($boolean) {
                    foreach ($values as $value) {
                        $query->where(function ($query) use ($argument, $value){
                            return $this->resolve($argument, $query, null, $value);
                        }, null, null, $boolean);
                    }
                    return $query;
                }
            ]
        ];
    }

    public function convertType(string $name, string $type, string $originalType) {
        switch ($type) {
            case "ID":
                return [
                    "{$name}" => self::equals($name, Type::id()),
                    "{$name}_not" => self::notEquals($name, Type::id()),
                    "{$name}_in" => self::in($name, Type::id()),
                    "{$name}_not_in" => self::notIn($name, Type::id()),
                    "{$name}_contains" => self::contains($name, Type::id()),
                    "{$name}_not_contains" => self::notContains($name, Type::id()),
                    "{$name}_starts_with" => self::startsWith($name, Type::id()),
                    "{$name}_not_starts_with" => self::startsNotWith($name, Type::id()),
                    "{$name}_ends_with" => self::endsWith($name, Type::id()),
                    "{$name}_not_ends_with" => self::endsNotWith($name, Type::id()),
                    "{$name}_lt" => self::lessThan($name, Type::id()),
                    "{$name}_lte" => self::lessThanEquals($name, Type::id()),
                    "{$name}_gt" => self::greaterThan($name, Type::id()),
                    "{$name}_gtq" => self::greaterThanEquals($name, Type::id()),
                ];
            case "String":
                return [
                    "{$name}" => self::equals($name, Type::string()),
                    "{$name}_not" => self::notEquals($name, Type::string()),
                    "{$name}_in" => self::in($name, Type::string()),
                    "{$name}_not_in" => self::notIn($name, Type::string()),
                    "{$name}_contains" => self::contains($name, Type::string()),
                    "{$name}_not_contains" => self::notContains($name, Type::string()),
                    "{$name}_starts_with" => self::startsWith($name, Type::string()),
                    "{$name}_not_starts_with" => self::startsNotWith($name, Type::string()),
                    "{$name}_ends_with" => self::endsWith($name, Type::string()),
                    "{$name}_not_ends_with" => self::endsNotWith($name, Type::string()),
                ];
            case "Int":
                return [
                    "{$name}" => self::equals($name, Type::int()),
                    "{$name}_not" => self::notEquals($name, Type::int()),
                    "{$name}_in" => self::in($name, Type::int()),
                    "{$name}_not_in" => self::notIn($name, Type::int()),
                    "{$name}_lt" => self::lessThan($name, Type::int()),
                    "{$name}_lte" => self::lessThanEquals($name, Type::int()),
                    "{$name}_gt" => self::greaterThan($name, Type::int()),
                    "{$name}_gte" => self::greaterThanEquals($name, Type::int()),
                ];
            case "Boolean":
                return [
                    "{$name}" => self::equals($name, Type::boolean()), //TODO maybe use IS for compare
                    "{$name}_not" => self::notEquals($name, Type::boolean()),
                ];
            case "Float":
                return [
                    "{$name}" => self::equals($name, Type::float()),
                    "{$name}_not" => self::notEquals($name, Type::float()),
                    "{$name}_in" => self::in($name, Type::float()),
                    "{$name}_not_in" => self::notIn($name, Type::float()),
                    "{$name}_lt" => self::lessThan($name, Type::float()),
                    "{$name}_lte" => self::lessThanEquals($name, Type::float()),
                    "{$name}_gt" => self::greaterThan($name, Type::float()),
                    "{$name}_gte" => self::greaterThanEquals($name, Type::float()),
                ];
        }

        /** @var InputObjectType $typeObject */
        $typeObject = schema()->instance("{$type}Filter");

        if(!is_null($typeObject)) {
            return [
                "{$name}" => self::createType(
                    $typeObject,
                    "",
                    function ($query, $values, $argument) use ($typeObject, $type, $name, $originalType) {
                        foreach ($values as $key => $value) {
                            dd(schema()->instance($originalType)->getField($name));

                            $relation = $this->directiveArgValue(
                                $this->fieldDirective(schema()->instance($originalType)->getField($name), 'belongsTo'),
                                'relation',
                                $value->getField()->name->value
                            );
                            dd($relation);

                            dd($relation);
                            dd(graphql()->nodes()->FindModelByType($type));
                            dd($this->resolve($typeObject, $query, null, $values)->toSql());
                        }
                    }
                )
            ];
        }
        return [];
    }

    public static function createType(Type $type, string $description, callable $method) {
        return [
            'type' => $type,
            'description' => $description,
            'resolve' => $method
        ];
    }

    public static function equals($name, Type $type)
    {
        return self::createType(
            $type,
            "matches all nodes with exact value",
            function($query, $value) use ($name) {
                return $query->where($name, $value);
            });
    }

    public static function notEquals($name, Type $type)
    {
        return self::createType(
            $type,
            "matches all nodes with different value",
            function($query, $value) use ($name) {
                return $query->where($name, "!=", $value);
            });
    }

    public static function in($name, Type $type)
    {
        return self::createType(
            Type::listOf(Type::nonNull($type)),
            "matches all nodes with value in the passed list",
            function($query, $value) use ($name) {
                return $query->whereIn($name, $value);
            });
    }

    public static function notIn(string $name, Type $type )
    {
        return self::createType(
            Type::listOf(Type::nonNull($type)),
            "matches all nodes with value not in the passed list",
            function ($query, $value) use ($name) {
                return $query->whereNotIn($name, $value);
            });
    }

    public static function contains(string $name, Type $type )
    {
        return self::createType(
            $type,
            "matches all nodes with a value that contains given substring",
            function($query, $value) use ($name) {
                return $query->where($name, 'LIKE', "%{$value}%");
            });
    }

    public static function notContains(string $name, Type $type )
    {
        return self::createType(
            $type,
            "matches all nodes with a value that does not contain given substring",
            function($query, $value) use ($name) {
                return $query->where($name, 'NOT LIKE', "%{$value}%");
            });
    }

    public static function startsWith(string $name, Type $type )
    {
        return self::createType(
            $type,
            "matches all nodes with a value that starts with given substring",
            function($query, $value) use ($name) {
                return $query->where($name, 'LIKE', "{$value}%");
            });
    }

    public static function startsNotWith(string $name, Type $type )
    {
        return self::createType(
            Type::string(),
            "matches all nodes with a value that does not start with given substring",
            function($query, $value) use ($name) {
                return $query->where($name, 'NOT LIKE', "{$value}%");
            });
    }

    public static function endsWith(string $name, Type $type )
    {
        return self::createType(
            $type,
            "matches all nodes with a value that ends with given substring",
            function($query, $value) use ($name) {
                return $query->where($name, 'LIKE', "%{$value}");
            });
    }

    public static function endsNotWith(string $name, Type $type )
    {
        return self::createType(
            $type,
            "matches all nodes with a value that does not end with given substring",
            function($query, $value) use ($name) {
                return $query->where($name, 'NOT LIKE', "%{$value}");
            });
    }

    public static function lessThan(string $name, Type $type )
    {
        return self::createType(
            $type,
            "matches all nodes with lesser value",
            function($query, $value) use ($name) {
                return $query->where($name, '>', "%{$value}");
            });
    }

    public static function lessThanEquals(string $name, Type $type )
    {
        return self::createType(
            $type,
            "matches all nodes with lesser or equal value",
            function($query, $value) use ($name) {
                return $query->where($name, '>=', "%{$value}");
            });
    }

    public static function greaterThan(string $name, Type $type )
    {
        return self::createType(
            $type,
            "matches all nodes with greater value",
            function($query, $value) use ($name) {
                return $query->where($name, '<', "%{$value}");
            });
    }

    public static function greaterThanEquals(string $name, Type $type )
    {
        return self::createType(
            $type,
            "matches all nodes with greater value or equal value",
            function($query, $value) use ($name) {
                return $query->where($name, '<=', "%{$value}");
            });
    }
}