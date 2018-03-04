<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use Nuwave\Lighthouse\Schema\Resolvers\FieldTypeResolver;
use Nuwave\Lighthouse\Support\Traits\CanExtendTypes;
use Nuwave\Lighthouse\Support\Traits\CanParseTypes;

class SchemaBuilder
{
    use CanExtendTypes, CanParseTypes;

    /**
     * Collection of schema enums.
     *
     * @var array
     */
    protected $enums = [];

    /**
     * Collection of schema scalars.
     *
     * @var array
     */
    protected $scalars = [];

    /**
     * Collection of schema interfaces.
     *
     * @var array
     */
    protected $interfaces = [];

    /**
     * Collection of schema types.
     *
     * @var array
     */
    protected $types = [];

    /**
     * Collection of schema input.
     *
     * @var array
     */
    protected $input = [];

    /**
     * Collection of schema unions.
     *
     * @var array
     */
    protected $unions = [];

    /**
     * Collection of schema queries.
     *
     * @var array
     */
    protected $queries = [];

    /**
     * Collection of schema mutations.
     *
     * @var array
     */
    protected $mutations = [];

    /**
     * Document node to parse.
     *
     * @var DocumentNode
     */
    protected $document;

    /**
     * Generate a GraphQL Schema.
     *
     * @param string $schema
     *
     * @return mixed
     */
    public function build($schema)
    {
        $this->register($schema);
        $registered = $this->getRegisteredTypes();
        $query = collect($registered)->firstWhere('name', 'Query');
        $mutation = collect($registered)->firstWhere('name', 'Mutation');
        $types = collect($registered)->filter(function ($type) {
            return ! in_array($type->name, ['Query', 'Mutation']);
        })->toArray();

        return new Schema(compact('query', 'mutation', 'types'));
    }

    /**
     * Parse schema definitions.
     *
     * @param string $schema
     *
     * @return \Illuminate\Support\Collection
     */
    public function register($schema)
    {
        $document = $this->parseSchema($schema);

        $this->setTypes($document);
        $this->setQuery();
        $this->setMutation();

        collect(array_merge(
            $this->getRegisteredTypes()
        ))->each(function ($type) {
            $this->unpackType($type);
        });

        return collect(array_merge(
            $this->getRegisteredTypes(),
            $this->mutations,
            $this->queries
        ));
    }

    public function setQuery()
    {
        $query = collect($this->getRegisteredTypes())
            ->first(function ($type) {
                return 'Query' === $type->name;
            }, $this->generateQuery());

        $query->config['fields'] = $this->queries;
        $this->type($query);
    }

    public function setMutation()
    {
        $mutation = collect($this->getRegisteredTypes())
            ->first(function ($type) {
                return 'Mutation' === $type->name;
            }, $this->generateMutation());

        $mutation->config['fields'] = $this->mutations;
        $this->type($mutation);
    }

    public function generateMutation()
    {
        return new ObjectType([
            'name' => 'Mutation',
            'fields' => $this->mutations,
        ]);
    }

    public function generateQuery()
    {
        return new ObjectType([
            'name' => 'Query',
            'fields' => $this->queries,
        ]);
    }

    /**
     * Resolve instance by name.
     *
     * @param string $type
     *
     * @return mixed
     */
    public function instance($type)
    {
        return collect($this->getRegisteredTypes())
        ->first(function ($instance) use ($type) {
            return $instance->name === $type;
        });
    }

    /**
     * Add type to register.
     *
     * @param ObjectType|array $type
     */
    public function type($type)
    {
        $this->types = is_array($type)
            ? array_merge($this->types, $type)
            : array_merge($this->types, [$type]);
    }

    /**
     * Set schema types.
     *
     * @param DocumentNode $document
     */
    protected function setTypes(DocumentNode $document)
    {
        $enums = $this->getEnums($document);
        $interfaces = $this->getInterfaces($document);
        $scalars = $this->getScalars($document);
        $types = $this->getObjectTypes($document);
        $inputs = $this->getInputTypes($document);
        $mutations = $this->getMutations($document);
        $queries = $this->getQueries($document);

        $this->enums = array_merge($this->enums, $enums);
        $this->interfaces = array_merge($this->interfaces, $interfaces);
        $this->scalars = array_merge($this->scalars, $scalars);
        $this->types = array_merge($this->types, $types);
        $this->input = array_merge($this->input, $inputs);
        $this->mutations = array_merge($this->mutations, $mutations);
        $this->queries = array_merge($this->queries, $queries);

        $this->attachTypeExtensions(
            $this->definitions($document),
            $this->getRegisteredTypes()
        );

        $this->mutations = array_merge($this->mutations, $this->getMutationExtensions(
            $this->definitions($document)
        ));

        $this->queries = array_merge($this->queries, $this->getQueryExtensions(
            $this->definitions($document)
        ));
    }

    /**
     * Get registered types.
     *
     * @return array
     */
    protected function getRegisteredTypes()
    {
        return array_merge(
            $this->types,
            $this->input,
            $this->scalars,
            $this->enums,
            $this->interfaces
        );
    }

    /**
     * Unpack type (fields and type).
     *
     * @param mixed $type
     *
     * @return mixed
     */
    public function unpackType($type)
    {
        if ($type instanceof Type && array_has($type->config, 'fields')) {
            $fields = is_callable($type->config['fields'])
                ? $type->config['fields']()
                : $type->config['fields'];

            $type->config['fields'] = collect($fields)->map(function ($field, $name) {
                $type = array_get($field, 'type');

                array_set($field, 'type', is_callable($type)
                    ? FieldTypeResolver::unpack($type())
                    : FieldTypeResolver::unpack($type)
                );

                return $field;
            })->toArray();
        }

        return $type;
    }
}
