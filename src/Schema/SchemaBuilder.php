<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\TypeExtensionDefinitionNode;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use GraphQL\Utils\BuildSchema;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\Factories\NodeFactory;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Contracts\GraphQl\Kind;
use Nuwave\Lighthouse\Support\Contracts\GraphQl\Node;
use Nuwave\Lighthouse\Support\Contracts\GraphQl\Type as TypeInterface;
use Nuwave\Lighthouse\Support\Traits\CanParseTypes;
use Nuwave\Lighthouse\Support\Traits\HandlesTypes;
use Nuwave\Lighthouse\Support\Webonyx\Type;

class SchemaBuilder
{
    use CanParseTypes, HandlesTypes;

    /**
     * Definition weights.
     *
     * @var array
     */
    protected $weights = [
        Kind::Scalar => 0,
        Kind::Interface => 1,
        Kind::Union => 2,
    ];

    /**
     * Collection of schema Types.
     *
     * @var Collection
     */
    protected $types;

    /**
     * Custom (client) directives.
     *
     * @var array
     */
    protected $directives = [];

    /**
     * SchemaBuilder constructor.
     */
    public function __construct()
    {
        $this->types = collect();
    }


    /**
     * Generate a GraphQL Schema.
     *
     * @param string $schema
     *
     * @return mixed
     */
    public function build($schema)
    {
        /** @var Collection $types */
        $types = $this->register($schema);
        $query = optional($types->firstWhere('name', 'Query'))->toGraphQlType();
        $mutation = optional($types->firstWhere('name', 'Mutation'))->toGraphQlType();
        $subscription = optional($types->firstWhere('name', 'Subscription'))->toGraphQlType();

        //dd($query->config['fields']);
        /*
        $query = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'hello' => [
                    'type' => \GraphQL\Type\Definition\Type::string(),
                    'resolve' => function() {
                        return 'Hello World!';
                    }
                ],
            ]
        ]);
        */

        $types = $types->whereNotIn('name', ['Query', 'Mutation', 'Subscription'])->map(function (TypeInterface $type) {
            return $type->toGraphQlType();
        })->all();
        $directives = $this->directives;
        $typeLoader = function ($name) {
            return $this->instance($name);
        };

        $schema = new Schema([
            'query' => $query
        ]);

        $schema->assertValid();


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
        $document = $this->parseSchema($schema);

        $this->setTypes($document);
        $this->extendTypes($document);
        $this->setDirectives($document);
        $this->injectNodeField();

        return $this->types;
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
        return collect($this->types)->firstWhere('name', $type);
    }

    /**
     * Get all registered Types.
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
     * @param ObjectType|array $types
     */
    public function type($types)
    {
        $types = is_array($types) ? $types : [$types];
        $types = array_map(function ($type) {
            return graphql()->typeRepository()->fromDriver($type);
        }, $types);

        $this->types = $this->types->merge($types);
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
     * Set schema Types.
     *
     * @param Node $document
     */
    protected function setTypes(Node $document)
    {
        $types = $document->definitions()
            ->whereNotIn('kind', [Kind::Extension, Kind::Directive])
            ->map(function (Node $node){
                return app(NodeFactory::class)->handle(new NodeValue($node));
            });

        // NOTE: We don't assign this above because new Types may be
        // declared by directives.
        $this->types = $this->types->merge($types);
    }

    /**
     * Set custom client directives.
     *
     * @param DocumentNode $document
     *
     * @return array
     */
    protected function setDirectives(Node $document)
    {
        $this->directives = $document->definitions()->filter(function ($node) {
            return $node instanceof DirectiveDefinitionNode;
        })->map(function (Node $node) {
            return app(NodeFactory::class)->handle(new NodeValue($node));
        })->toArray();
    }

    /**
     * Extend registered Types.
     *
     * @param DocumentNode $document
     */
    protected function extendTypes(Node $document)
    {

        $document->definitions()->filter(function ($def) {
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
}
