<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Language\AST\DefinitionNode;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\TypeExtensionDefinitionNode;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use Nuwave\Lighthouse\Schema\Factories\NodeFactory;
use Nuwave\Lighthouse\Schema\Utils\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Contracts\SchemaGenerator;
use Nuwave\Lighthouse\Support\Traits\HandlesTypes;

class SchemaBuilder
{
    use HandlesTypes;

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
     * Custom (client) directives.
     *
     * @var array
     */
    protected $directives = [];

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
        $subscription = $types->firstWhere('name', 'Subscription');

        $types = $types->filter(function ($type) {
            return ! in_array($type->name, ['Query', 'Mutation', 'Subscription']);
        })->toArray();

        $directives = $this->directives;
        $typeLoader = function ($name) {
            return $this->instance($name);
        };

        $schema =new Schema(compact(
            'query',
            'mutation',
            'subscription',
            'types',
            'directives',
            'typeLoader'
        ));
        dd($schema->assertValid());
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
     * Parse schema definitions.
     *
     * @param string $schema
     *
     * @return \Illuminate\Support\Collection
     */
    public function register($schema)
    {
        $document = $schema instanceof DocumentNode
            ? new DocumentAST($schema)
            : DocumentAST::parse($schema);

        $document = $this->applySchemaGenerators($document);

        $this->registerTypes($document);
        $this->extendTypes($document);
        $this->registerDirectives($document);
        $this->injectNodeField();

        return collect($this->types);
    }

    /**
     * Enhance the schema document by applying the GenerateDirective.
     *
     * @param DocumentAST $document
     *
     * @return DocumentAST
     */
    protected function applySchemaGenerators(DocumentAST $document)
    {
        $originalDocument = $document;

        // todo generalize this to all parent types
        return collect($document->getQueryTypeDefinition()->fields)
            // Could generators be useful for other definitions? If so, maybe extend this filter
        ->reduce(function ($document, $definitionNode) use ($originalDocument) {
            $generators = directives()->generators($definitionNode);

            return $generators->reduce(function(DocumentAST $document, SchemaGenerator $generator) use ($originalDocument, $definitionNode){
                return $generator->handleSchemaGeneration($definitionNode, $document, $originalDocument);
            }, $document);
        }, $document);
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
     * Set schema types.
     *
     * @param DocumentAST $document
     */
    protected function registerTypes(DocumentAST $document)
    {
        $types = $document->definitions()->reject(function (DefinitionNode $node) {
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
     * @param DocumentAST $document
     */
    protected function registerDirectives(DocumentAST $document)
    {
        $this->directives = $document->directives()->map(function (Node $node) {
            return app(NodeFactory::class)->handle(new NodeValue($node));
        })->toArray();
    }

    /**
     * Extend registered types.
     *
     * @param DocumentAST $document
     */
    protected function extendTypes(DocumentAST $document)
    {
        $document->typeExtensions()->each(function (TypeExtensionDefinitionNode $extension) {
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

        $this->extendTypes(DocumentAST::parse('
        extend type Query {
            node(id: ID!): Node
                @field(resolver: "Nuwave\\\Lighthouse\\\Support\\\Http\\\GraphQL\\\Queries\\\NodeQuery@resolve")
        }
        '));
    }
}
