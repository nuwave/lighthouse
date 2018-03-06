<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\TypeExtensionDefinitionNode;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use Nuwave\Lighthouse\Schema\Factories\NodeFactory;
use Nuwave\Lighthouse\Schema\Resolvers\FieldTypeResolver;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Traits\CanParseTypes;

class SchemaBuilder
{
    use CanParseTypes;

    /**
     * Collection of schema types.
     *
     * @var array
     */
    protected $types = [];

    /**
     * Generate a GraphQL Schema.
     *
     * @param string $schema
     *
     * @return mixed
     */
    public function build($schema)
    {
        $types = $this->register($schema);
        $query = $types->firstWhere('name', 'Query');
        $mutation = $types->firstWhere('name', 'Mutation');

        $types = $types->filter(function ($type) {
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
        $this->extendTypes($document);

        while ($this->hasPackedTypes()) {
            collect($this->types)->each(function ($type) {
                $this->unpackType($type);
            });
        }

        return collect($this->types);
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
        return collect($this->types)
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
        $types = collect($document->definitions)->reject(function ($node) {
            return $node instanceof TypeExtensionDefinitionNode;
        })->map(function (Node $node) {
            return app(NodeFactory::class)->handle(new NodeValue($node));
        })->toArray();

        // NOTE: We don't assign this above because new types may be
        // declared by directives.
        $this->types = array_merge($this->types, $types);
    }

    /**
     * Extend registered types.
     *
     * @param DocumentNode $document
     */
    protected function extendTypes(DocumentNode $document)
    {
        collect($document->definitions)->filter(function ($def) {
            return $def instanceof TypeExtensionDefinitionNode;
        })->each(function (TypeExtensionDefinitionNode $extension) {
            $name = $extension->definition->name->value;

            if ($type = collect($this->types)->firstWhere('name', $name)) {
                app(NodeFactory::class)->extend($extension, $type);
            }
        });
    }

    /**
     * Check if schema still has packed types.
     *
     * @return bool
     */
    protected function hasPackedTypes()
    {
        return collect($this->types)->reduce(function ($packed, $type) {
            return $packed ?: $this->isTypePacked($type);
        }, false);
    }

    /**
     * Check if type has been unpacked.
     *
     * @param Type $type
     *
     * @return bool
     */
    protected function isTypePacked(Type $type)
    {
        if (is_callable(array_get($type->config, 'fields'))) {
            return true;
        }

        return collect(array_get($type->config, 'fields'), [])->reduce(function ($packed, $field) {
            if ($packed) {
                return true;
            } elseif (is_callable(array_get($field, 'type'))) {
                return true;
            }

            $fieldType = array_get($field, 'type');
            if (method_exists($fieldType, 'getWrappedType')) {
                $wrappedType = $fieldType->getWrappedType();

                return is_callable($wrappedType);
            }

            return false;
        }, false);

        return false;
    }

    /**
     * Unpack type (fields and type).
     *
     * @param mixed $type
     *
     * @return mixed
     */
    protected function unpackType($type)
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
