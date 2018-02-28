<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\TypeExtensionDefinitionNode;
use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Schema\ExtensionFactory;
use Nuwave\Lighthouse\Schema\MutationFactory;
use Nuwave\Lighthouse\Schema\QueryFactory;

class SchemaBuilder
{
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
        $this->document = Parser::parse($schema);

        $this->setTypes();

        return collect(array_merge(
            $this->enums,
            $this->interfaces,
            $this->scalars,
            $this->types,
            $this->input,
            $this->mutations,
            $this->queries
        ));
    }

    /**
     * Set schema types.
     */
    protected function setTypes()
    {
        $this->setEnums();
        $this->setInterfaces();
        $this->setScalars();
        $this->setObjectTypes();
        $this->setInputTypes();
        $this->setMutations();
        $this->setQueries();
        $this->attachTypeExtensions();
        $this->attachMutationExtensions();
        $this->attachQueryExtensions();
    }

    /**
     * Set enum types.
     */
    protected function setEnums()
    {
        $this->enums = $this->definitions()->filter(function ($def) {
            return $def instanceof EnumTypeDefinitionNode;
        })->map(function (EnumTypeDefinitionNode $enum) {
            return NodeFactory::enum($enum);
        })->toArray();
    }

    /**
     * Set interface types.
     */
    protected function setInterfaces()
    {
        $this->interfaces = $this->definitions()->filter(function ($def) {
            return $def instanceof InterfaceTypeDefinitionNode;
        })->map(function (InterfaceTypeDefinitionNode $interface) {
            return NodeFactory::interface($interface);
        })->toArray();
    }

    /**
     * Set scalar types.
     */
    protected function setScalars()
    {
        $this->scalars = $this->definitions()->filter(function ($def) {
            return $def instanceof ScalarTypeDefinitionNode;
        })->map(function (ScalarTypeDefinitionNode $scalar) {
            return NodeFactory::scalar($scalar);
        })->toArray();
    }

    /**
     * Set object types.
     */
    protected function setObjectTypes()
    {
        $this->types = $this->objectTypes()
        ->filter(function (ObjectTypeDefinitionNode $objectType) {
            return ! in_array($objectType->name->value, ['Mutation', 'Query']);
        })->map(function (ObjectTypeDefinitionNode $objectType) {
            return NodeFactory::objectType($objectType);
        })->toArray();
    }

    /**
     * Set input types.
     */
    protected function setInputTypes()
    {
        $this->input = $this->definitions()->filter(function ($def) {
            return $def instanceof InputObjectTypeDefinitionNode;
        })->map(function (InputObjectTypeDefinitionNode $input) {
            return NodeFactory::inputObjectType($input);
        })->toArray();
    }

    /**
     * Set mutation fields.
     */
    protected function setMutations()
    {
        $this->mutations = $this->objectTypes()
        ->filter(function (ObjectTypeDefinitionNode $objectType) {
            return 'Mutation' === $objectType->name->value;
        })->map(function (ObjectTypeDefinitionNode $objectType) {
            return collect($objectType->fields)->toArray();
        })->collapse()->mapWithKeys(function (FieldDefinitionNode $mutation) {
            return [data_get($mutation, 'name.value') => MutationFactory::resolve($mutation)];
        })->toArray();
    }

    /**
     * Set mutation fields.
     */
    protected function setQueries()
    {
        $this->queries = $this->objectTypes()
        ->filter(function (ObjectTypeDefinitionNode $objectType) {
            return 'Query' === $objectType->name->value;
        })->map(function (ObjectTypeDefinitionNode $objectType) {
            return collect($objectType->fields)->toArray();
        })->collapse()->mapWithKeys(function (FieldDefinitionNode $mutation) {
            return [data_get($mutation, 'name.value') => QueryFactory::resolve($mutation)];
        })->toArray();
    }

    /**
     * Attach extensions to types.
     */
    protected function attachTypeExtensions()
    {
        $this->definitions()->filter(function ($def) {
            return $def instanceof TypeExtensionDefinitionNode;
        })->each(function (TypeExtensionDefinitionNode $extension) {
            collect($this->types)->filter(function ($type) use ($extension) {
                return $type->name === $extension->definition->name->value;
            })->each(function ($type) use ($extension) {
                ExtensionFactory::extend($extension, $type);
            });
        });
    }

    /**
     * Attach extensions to mutations.
     */
    protected function attachMutationExtensions()
    {
        $this->definitions()->filter(function ($def) {
            return $def instanceof TypeExtensionDefinitionNode;
        })->filter(function (TypeExtensionDefinitionNode $extension) {
            return 'Mutation' === $extension->definition->name->value;
        })->each(function (TypeExtensionDefinitionNode $extension) {
            $this->mutations = array_merge(
                $this->mutations,
                ExtensionFactory::extractFields($extension)
            );
        });
    }

    /**
     * Attach extensions to queries.
     */
    protected function attachQueryExtensions()
    {
        $this->definitions()->filter(function ($def) {
            return $def instanceof TypeExtensionDefinitionNode;
        })->filter(function (TypeExtensionDefinitionNode $extension) {
            return 'Query' === $extension->definition->name->value;
        })->each(function (TypeExtensionDefinitionNode $extension) {
            $this->queries = array_merge(
                $this->queries,
                ExtensionFactory::extractFields($extension)
            );
        });
    }

    /**
     * Get definitions from document.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function definitions()
    {
        return collect($this->document->definitions);
    }

    /**
     * Get object types from document.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function objectTypes()
    {
        return $this->definitions()->filter(function ($def) {
            return $def instanceof ObjectTypeDefinitionNode;
        });
    }
}
