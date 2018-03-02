<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
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
        // ...
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

        collect(array_merge(
            $this->getRegisteredTypes(),
            $this->mutations,
            $this->queries
        ))->each(function ($type) {
            $this->unpackType($type);
        });

        return collect($this->getRegisteredTypes());
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
        $this->enums = $this->getEnums($document);
        $this->interfaces = $this->getInterfaces($document);
        $this->scalars = $this->getScalars($document);
        $this->types = $this->getObjectTypes($document);
        $this->input = $this->getInputTypes($document);
        $this->mutations = $this->getMutations($document);
        $this->queries = $this->getQueries($document);

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
