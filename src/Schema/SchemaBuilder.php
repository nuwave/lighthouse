<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\TypeExtensionDefinitionNode;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use Nuwave\Lighthouse\Schema\Factories\NodeFactory;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Traits\CanParseTypes;
use Nuwave\Lighthouse\Support\Traits\HandlesTypes;

class SchemaBuilder
{
    use CanParseTypes, HandlesTypes;

    /**
     * Definition weights.
     *
     * @var array
     */
    protected $weights = [
        \GraphQL\Language\AST\ScalarTypeDefinitionNode::class => 0,
        \GraphQL\Language\AST\InterfaceTypeDefinitionNode::class => 1,
        \GraphQL\Language\AST\UnionTypeDefinitionNode::class => 2,
    ];

    /**
     * Collection of schema types.
     *
     * @var array
     */
    protected $types = [];

    /**
     * Generate a GraphQL Schema.
     *
     * @param string|DocumentNode $schema
     *
     * @return Schema
     */
    public function build($schema)
    {
        $schema = $this->parseSchemaIfNecessary($schema);
        $schema = $this->applyGenerateDirective($schema);

        $types = $this->register($schema);
        $query = $types->firstWhere('name', 'Query');
        $mutation = $types->firstWhere('name', 'Mutation');
        $subscription = $types->firstWhere('name', 'Subscription');

        $types = $types->filter(function ($type) {
            return ! in_array($type->name, ['Query', 'Mutation', 'Subscription']);
        })->toArray();

        $directives = $this->getCustomClientDirectives($schema);
        $typeLoader = function ($name) {
            return $this->instance($name);
        };

        return new Schema(compact(
            'query',
            'mutation',
            'subscription',
            'types',
            'directives',
            'typeLoader'
        ));
    }

    /**
     * Register the types from the enhanced schema.
     *
     * @param DocumentNode $schema
     *
     * @return \Illuminate\Support\Collection
     */
    public function register($schema)
    {
        // TODO remove this check after tests are switched to using build()
        $schema = $this->parseSchemaIfNecessary($schema);

        $this->setTypes($schema);
        $this->extendTypes($schema);
        $this->injectNodeField();

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
     * Get all registered types.
     *
     * @return array
     */
    public function types()
    {
        return $this->types;
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
     * Serialize AST.
     *
     * @return string
     */
    public function serialize()
    {
        $schema = collect($this->types)->map(function ($type) {
            return $this->serializeableType($type);
        })->toArray();

        return serialize($schema);
    }

    /**
     * Unserialize AST.
     *
     * @param string $schema
     *
     * @return \Illuminate\Support\Collection
     */
    public function unserialize($schema)
    {
        $this->types = collect(unserialize($schema))->map(function ($type) {
            return $this->unpackType($type);
        });

        return collect($this->types);
    }

    /**
     * Enhance the schema document by applying the GenerateDirective.
     *
     * @param DocumentNode $document
     *
     * @return DocumentNode
     */
    protected function applyGenerateDirective(DocumentNode $document)
    {
        $originalDocument = $document;

        return collect($document->definitions)->filter(function ($node) {
            // Could generators be useful for other definitions? If so, maybe extend this filter
            return $node instanceof ObjectTypeDefinitionNode;
        })->reduce(function ($document, $definitionNode) use ($originalDocument) {
            $generator = directives()->getGenerateDirective($definitionNode);

            return $generator ? $generator->generate($definitionNode, $document, $originalDocument) : $document;
        }, $document);
    }

    /**
     * Set schema types.
     *
     * @param DocumentNode $document
     */
    protected function setTypes(DocumentNode $document)
    {
        $types = collect($document->definitions)->reject(function ($node) {
            return $node instanceof TypeExtensionDefinitionNode
                || $node instanceof DirectiveDefinitionNode;
        })->sortBy(function ($node) {
            return array_get($this->weights, get_class($node), 9);
        })->map(function (Node $node) {
            return app(NodeFactory::class)->handle(new NodeValue($node));
        })->toArray();

        // NOTE: We don't assign this above because new types may be
        // declared by directives.
        $this->types = array_merge($this->types, $types);
    }

    /**
     * Set custom client directives.
     *
     * @param DocumentNode $document
     *
     * @return array
     */
    protected function getCustomClientDirectives(DocumentNode $document)
    {
        return collect($document->definitions)->filter(function ($node) {
            return $node instanceof DirectiveDefinitionNode;
        })->map(function (Node $node) {
            return app(NodeFactory::class)->handle(new NodeValue($node));
        })->toArray();
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
                $value = new NodeValue($extension);

                app(NodeFactory::class)->handle($value->setType($type));
            }
        });
    }

    /**
     * Inject node field into Query.
     */
    protected function injectNodeField()
    {
        if (is_null(config('lighthouse.global_id_field'))) {
            return;
        }

        if (! $query = $this->instance('Query')) {
            return;
        }

        $this->extendTypes($this->parseSchema('
        extend type Query {
            node(id: ID!): Node
                @field(resolver: "Nuwave\\\Lighthouse\\\Support\\\Http\\\GraphQL\\\Queries\\\NodeQuery@resolve")
        }
        '));
    }

    /**
     * @param $schema
     *
     * @return DocumentNode
     */
    protected function parseSchemaIfNecessary($schema)
    {
        return $schema instanceof DocumentNode
            ? $schema
            : $this->parseSchema($schema);
    }
}
