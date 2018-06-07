<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Language\AST\DefinitionNode;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\TypeExtensionDefinitionNode;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use GraphQL\Utils\SchemaPrinter;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Factories\NodeFactory;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
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
     * Build an executable schema from AST.
     *
     * @param DocumentAST $schema
     *
     * @return Schema
     */
    public function build($schema)
    {
        $this->types = $this->convertTypes($schema);

        $query = $this->types->firstWhere('name', 'Query');
        $mutation = $this->types->firstWhere('name', 'Mutation');
        $subscription = $this->types->firstWhere('name', 'Subscription');

        $types = $this->types->filter(function ($type) {
            return ! in_array($type->name, ['Query', 'Mutation', 'Subscription']);
        })->toArray();

        $directives = $this->convertDirectives($schema)->toArray();
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
     * @return Collection
     */
    public function unserialize($schema)
    {
        $this->types = collect(unserialize($schema))->map(function ($type) {
            return $this->unpackType($type);
        });

        return collect($this->types);
    }

    /**
     * Convert definitions to types.
     *
     * @param DocumentAST $document
     *
     * @return Collection
     */
    public function convertTypes(DocumentAST $document)
    {
        return $document->definitions()->reject(function (DefinitionNode $node) {
            return $node instanceof TypeExtensionDefinitionNode
                || $node instanceof DirectiveDefinitionNode;
        })->sortBy(function (DefinitionNode $node) {
            return array_get($this->weights, get_class($node), 9);
        })->map(function (DefinitionNode $node) {
            return app(NodeFactory::class)->handle(new NodeValue($node));
        });
    }

    /**
     * Set custom client directives.
     *
     * @param DocumentAST $document
     *
     * @return Collection
     */
    protected function convertDirectives(DocumentAST $document)
    {
        return $document->directives()->map(function (Node $node) {
            return app(NodeFactory::class)->handle(new NodeValue($node));
        });
    }
}
