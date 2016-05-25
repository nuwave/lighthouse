<?php

namespace Nuwave\Relay\Support\Definition;

use ReflectionClass;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\IDType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;

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
     * @var \Illuminate\Support\DefinitionsCollection
     */
    protected $fields;

    /**
     * Hidden type field.
     *
     * @var \Illuminate\Support\DefinitionsCollection
     */
    protected $hiddenFields;

    /**
     * If fields should be camel cased.
     *
     * @var boolean
     */
    protected $camelCase = false;

    /**
     * Create new instance of eloquent type.
     *
     * @param Model $model
     */
    public function __construct(Model $model, $name = '')
    {
        $this->name         = $name;
        $this->fields       = collect();
        $this->hiddenFields = collect($model->getHidden())->flip();
        $this->model        = $model;
        $this->camelCase    = config('relay.camel_case', false);
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
            $this->fields = $fields;
        } else {
            $this->schemaFields();
            $graphql->cache()->store($name, $this->fields);
        }

        if (method_exists($this->model, 'graphqlFields')) {
            $this->eloquentFields(collect($this->model->graphqlFields()));
        }

        if (method_exists($this->model, $this->getTypeMethod())) {
            $method = $this->getTypeMethod();
            $this->eloquentFields(collect($this->model->{$method}()));
        }

        return new ObjectType([
            'name'        => $name,
            'description' => $this->getDescription(),
            'fields'      => $this->fields->toArray()
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
            $this->eloquentFields(collect($this->model->graphqlFields()));
        }

        if (method_exists($this->model, $this->getTypeMethod())) {
            $method = $this->getTypeMethod();
            $this->eloquentFields(collect($this->model->{$method}()));
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
            if (!$this->skipField($key)) {
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
    protected function schemaFields()
    {
        $table = $this->model->getTable();
        $schema = $this->model->getConnection()->getSchemaBuilder();
        $columns = collect($schema->getColumnListing($table));

        $columns->each(function ($column) use ($table, $schema) {
            if (!$this->skipField($column)) {
                $this->generateField(
                    $column,
                    $schema->getColumnType($table, $column)
                );
            }
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
        $type->name = $this->getName().'_String';

        if ($name === $this->model->getKeyName()) {
            $type = Type::id();
            $type->name = $this->getName().'_ID';
        } elseif ($colType === 'integer') {
            $type = Type::int();
            $type->name = $this->getName().'_Int';
        } elseif ($colType === 'float' || $colType === 'decimal') {
            $type = Type::float();
            $type->name = $this->getName().'_Float';
        } elseif ($colType === 'boolean') {
            $type = Type::boolean();
            $type->name = $this->getName().'_Boolean';
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

        if ($class == $namespace . 'NonNull') {
            return 'Type::nonNull('. $this->getRawType($type->getWrappedType()) .')';
        } elseif ($class == $namespace . 'IDType') {
            return 'Type::id()';
        } elseif ($class == $namespace . 'IntType') {
            return 'Type::int()';
        } elseif ($class == $namespace . 'BooleanType') {
            return 'Type::bool()';
        } elseif ($class == $namespace . 'FloatType') {
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
     * @return boolean
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
        $method = 'resolve' . studly_case($key) . 'Field';

        if (method_exists($this->model, $method)) {
            return array($this->model, $method);
        }

        return null;
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
}
