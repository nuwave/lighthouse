<?php

namespace Nuwave\Lighthouse\Support\Definition;

use ReflectionClass;
use Nuwave\Lighthouse\Schema\Generators\TypeGenerator;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\IDType;
use GraphQL\Type\Definition\ObjectType;

class EloquentType
{
    /**
     * Eloquent model.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $model;

    /**
     * Registered type name.
     *
     * @var string
     */
    protected $name;

    /**
     * Available fields.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $fields;

    /**
     * Hidden type field.
     *
     * @var \Illuminate\Support\DefinitionsCollection
     */
    protected $hiddenFields;

    /**
     * Type generator.
     *
     * @var TypeGenerator
     */
    protected $typeGenerator;

    /**
     * If fields should be camel cased.
     *
     * @var bool
     */
    protected $camelCase = false;

    /**
     * Create new instance of eloquent type.
     *
     * @param Model $model
     */
    public function __construct(Model $model, $name = '')
    {
        $this->name = $name;
        $this->fields = new Collection;
        $this->hiddenFields = Collection::make($model->getHidden())->flip();
        $this->model = $model;
        $this->camelCase = config('lighthouse.camel_case', false);
    }

    /**
     * Transform eloquent model to graphql type.
     *
     * @return \GraphQL\Type\Definition\ObjectType
     */
    public function toType()
    {
        $graphql = app('graphql');
        $name = $this->getName();

        if ($fields = $graphql->cache()->get($name)) {
            $this->fields = $this->cachedFields($fields);
        } else {
            $this->schemaFields();
            $graphql->cache()->store($name, $this->fields);
        }

        if (method_exists($this->model, 'graphqlFields')) {
            $this->eloquentFields(new Collection($this->model->graphqlFields()));
        }

        if (method_exists($this->model, $this->getTypeMethod())) {
            $method = $this->getTypeMethod();
            $this->eloquentFields(new Collection($this->model->{$method}()));
        }

        return new ObjectType([
            'name'        => $name,
            'description' => $this->getDescription(),
            'fields'      => function () {
                return $this->fields->toArray();
            },
        ]);
    }

    /**
     * Get fields for model.
     *
     * @return \Illuminate\Support\DefinitionsCollection
     */
    public function rawFields()
    {
        $this->schemaFields();

        if (method_exists($this->model, 'graphqlFields')) {
            $this->eloquentFields(new Collection($this->model->graphqlFields()));
        }

        if (method_exists($this->model, $this->getTypeMethod())) {
            $method = $this->getTypeMethod();
            $this->eloquentFields(new Collection($this->model->{$method}()));
        }

        return $this->fields->transform(function ($field, $key) {
            $field['type'] = $this->getRawType($field['type']);

            return $field;
        });
    }

    /**
     * Convert eloquent defined fields.
     *
     * @param  \Illuminate\Support\Collection
     * @return array
     */
    public function eloquentFields(Collection $fields)
    {
        $fields->each(function ($field, $key) {
            if (! $this->skipField($key)) {
                $data = [];
                $data['type'] = $field['type'];
                $data['description'] = isset($field['description']) ? $field['description'] : null;

                if (isset($field['resolve'])) {
                    $data['resolve'] = $field['resolve'];
                } elseif ($method = $this->getModelResolve($key)) {
                    $data['resolve'] = $method;
                }

                $this->addField($key, $field);
            }
        });
    }

    /**
     * Create fields for type.
     *
     * @return void
     */
    public function schemaFields()
    {
        $platform = $this->model->getConnection()->getDoctrineSchemaManager()->getDatabasePlatform();
        $platform->registerDoctrineTypeMapping('enum', 'string');

        $table = $this->model->getTable();
        $schema = $this->model->getConnection()->getSchemaBuilder();
        $columns = new Collection($schema->getColumnListing($table));

        $columns->each(function ($column) use ($table, $schema) {
            if (! $this->skipField($column)) {
                $this->generateField(
                    $column,
                    $schema->getColumnType($table, $column)
                );
            }
        });
    }

    /**
     * Create fields from cache.
     *
     * Why do we need this: (https://github.com/webonyx/graphql-php/issues/31)
     *
     * @param  Collection $fields
     * @return \Illuminate\Support\Collection
     */
    protected function cachedFields(Collection $fields)
    {
        $namespace = 'GraphQL\\Type\\Definition\\';
        $generator = $this->typeGenerator();

        return $fields->filter(function ($field) {
            return isset($field['type']);
        })->map(function ($field) use ($namespace, $generator) {
            return [
                'type' => $generator->fromType($field['type']),
                'description' => $field['description'],
            ];
        });
    }

    /**
     * Generate type field from schema.
     *
     * @param  string $name
     * @param  string $colType
     * @return void
     */
    protected function generateField($name, $colType)
    {
        $field = [];
        $field['type'] = $this->resolveTypeByColumn($name, $colType);
        $field['description'] = isset($this->descriptions['name']) ? $this->descriptions[$name] : null;

        if ($name === $this->model->getKeyName()) {
            $field['description'] = $field['description'] ?: 'Primary id of type.';
        }

        if ($method = $this->getModelResolve($name)) {
            $field['resolve'] = $method;
        }

        $fieldName = $this->camelCase ? camel_case($name) : $name;

        $this->addField($fieldName, $field);
    }

    /**
     * Resolve field type by column info.
     *
     * @param  string $name
     * @param  string $colType
     * @return \GraphQL\Type\Definition\Type
     */
    protected function resolveTypeByColumn($name, $colType)
    {
        $type = Type::string();
        $type->name = 'String';

        if ($name === $this->model->getKeyName()) {
            $type = Type::id();
            $type->name = 'ID';
        } elseif ($colType === 'integer') {
            $type = Type::int();
            $type->name = 'Int';
        } elseif ($colType === 'float' || $colType === 'decimal') {
            $type = Type::float();
            $type->name = 'Float';
        } elseif ($colType === 'boolean') {
            $type = Type::boolean();
            $type->name = 'Boolean';
        }

        return $type;
    }

    /**
     * Get raw name for type.
     *
     * @param  Type   $type
     * @return string
     */
    protected function getRawType(Type $type)
    {
        $class = get_class($type);
        $namespace = 'GraphQL\\Type\\Definition\\';

        if ($class == $namespace.'NonNull') {
            return 'Type::nonNull('.$this->getRawType($type->getWrappedType()).')';
        } elseif ($class == $namespace.'IDType') {
            return 'Type::nonNull(Type::id())';
        } elseif ($class == $namespace.'IntType') {
            return 'Type::int()';
        } elseif ($class == $namespace.'BooleanType') {
            return 'Type::bool()';
        } elseif ($class == $namespace.'FloatType') {
            return 'Type::float()';
        }

        return 'Type::string()';
    }

    /**
     * Add field to collection.
     *
     * @param string $name
     * @param array $field
     */
    protected function addField($name, $field)
    {
        $name = $this->camelCase ? camel_case($name) : $name;

        $this->fields->put($name, $field);
    }

    /**
     * Check if field should be skipped.
     *
     * @param  string $field
     * @return bool
     */
    protected function skipField($name = '')
    {
        if ($this->hiddenFields->has($name) || $this->fields->has($name)) {
            return true;
        }

        return false;
    }

    /**
     * Check if model has resolve function.
     *
     * @param  string  $key
     * @return string|null
     */
    protected function getModelResolve($key)
    {
        $method = 'resolve'.studly_case($key).'Field';

        if (method_exists($this->model, $method)) {
            return [$this->model, $method];
        }
    }

    /**
     * Get name for type.
     *
     * @return string
     */
    protected function getName()
    {
        if ($this->name) {
            return studly_case($this->name);
        }

        return $this->model->name ?: ucfirst((new ReflectionClass($this->model))->getShortName());
    }

    /**
     * Get description of type.
     *
     * @return string
     */
    protected function getDescription()
    {
        return $this->model->description ?: null;
    }

    /**
     * Get method name for type.
     *
     * @return string
     */
    protected function getTypeMethod()
    {
        return 'graphql'.$this->getName().'Fields';
    }

    /**
     * Get field collection.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * Set local instance of type generator.
     *
     * @param TypeGenerator $generator
     */
    public function setTypeGenerator(TypeGenerator $generator)
    {
        $this->typeGenerator = $generator;
    }

    /**
     * Get instance of type genreator.
     *
     * @return TypeGenerator
     */
    public function typeGenerator()
    {
        return $this->typeGenerator ?: app(TypeGenerator::class);
    }
}
