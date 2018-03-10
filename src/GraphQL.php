<?php

namespace Nuwave\Lighthouse;

use Closure;
use GraphQL\Schema;
use GraphQL\GraphQL as GraphQLBase;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\DirectiveLocation;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\Field;
use Nuwave\Lighthouse\Schema\QueryParser;
use Nuwave\Lighthouse\Schema\FieldParser;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Nuwave\Lighthouse\Support\Cache\FileStore;
use Nuwave\Lighthouse\Support\Interfaces\Connection;
use Nuwave\Lighthouse\Support\Traits\Container\ScalarTypes;
use Nuwave\Lighthouse\Support\Traits\Container\QueryExecutor;

class GraphQL
{
    use QueryExecutor, ScalarTypes;

    /**
     * Instance of application.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * Instance of schema builder.
     *
     * @var \Nuwave\Lighthouse\Schema\SchemaBuilder
     */
    protected $schema;

    /**
     * Instance of cache.
     *
     * @var \Nuwave\Lighthouse\Support\Cache\FileStore
     */
    protected $cache;

    /**
     * Types that implement interfaces.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $typesWithInterfaces;

    /**
     * Create new instance of graphql container.
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     */
    public function __construct($app)
    {
        $this->app = $app;
        $this->typesWithInterfaces = new Collection;
    }

    /**
     * Generate GraphQL Schema.
     *
     * @return \GraphQL\Schema
     */
    public function buildSchema()
    {
        // Initialize types
        $this->types()->each(function ($type, $key) {
            $type = $this->type($key);

            if (method_exists($type, 'getInterfaces') && ! empty($type->getInterfaces())) {
                $this->typesWithInterfaces->push($type);
            }
        });

        $queryFields = $this->queries()->merge($this->connections()->toArray());
        $mutationFields = $this->mutations();
        $subscriptionFields = $this->subscriptions();

        $queryType = $this->generateSchemaType($queryFields, 'Query');
        $mutationType = $this->generateSchemaType($mutationFields, 'Mutation');
        $subscriptionType = $this->generateSchemaType($subscriptionFields, 'Subscription');

        return new Schema([
            'query' => $queryType,
            'mutation' => $mutationType,
            'subscription' => $subscriptionType,
            'types' => $this->typesWithInterfaces->all(),
            'directives' => array_merge(
                GraphQLBase::getInternalDirectives(),
                [$this->connectionDirective()]
            )
        ]);
    }

    /**
     * Generate type from collection of fields.
     *
     * @param  Collection $fields
     * @param  $string    $name
     * @return \GraphQL\Type\Definition\ObjectType|null
     */
    protected function generateSchemaType(Collection $fields, $name)
    {
        $typeFields = $fields->map(function ($field, $key) {
            if ($field instanceof Field) {
                return app($field->namespace)->toArray();
            }

            return $field;
        });

        if ($typeFields->count()) {
            return new ObjectType([
                'name' => $name,
                'fields' => $typeFields->toArray(),
            ]);
        }
    }

    /**
     * Generate a connection directive
     * TODO: Allow directives to be added to the schema by the user.
     * Currently this allows the use of the connection directive in Apollo client.
     *
     * @return Directive
     */
    protected function connectionDirective()
    {
        return new Directive([
            'name' => 'connection',
            'description' => '',
            'locations' => [DirectiveLocation::FIELD, DirectiveLocation::FIELD_DEFINITION],
            'args' => [
                new FieldArgument([
                    'name' => 'key',
                    'type' => Type::string(),
                    'description' => '',
                    'defaultValue' => ''
                ])
            ]
        ]);
    }

    /**
     * Extract instance from type registrar.
     *
     * @param  string $name
     * @param  bool $fresh
     * @return \GraphQL\Type\Definition\ObjectType
     */
    public function type($name, $fresh = false)
    {
        return $this->schema()->typeInstance($name, $fresh);
    }

    /**
     * Extract instance from connection registrar.
     *
     * @param  string $name
     * @param  string|null $parent
     * @param  bool $fresh
     * @return \Nuwave\Lighthouse\Support\Definition\Fields\ConnectionField
     */
    public function connection($name, $parent = null, $fresh = false)
    {
        $connection = $this->schema()->connectionInstance($name, $parent, $fresh);
        $connectionName = $name instanceof Connection ? $name->type() : $name;

        if (! $this->connections()->has($connectionName)) {
            $this->schema()->connection($connectionName, $connection);
        }

        return $connection;
    }

    /**
     * Extract instance from edge registrar.
     *
     * @param  string $name
     * @param  ObjectType $type
     * @param  bool $fresh
     * @return \Nuwave\Lighthouse\Support\Definition\Fields\EdgeField
     */
    public function edge($name, ObjectType $type = null, $fresh = false)
    {
        return $this->schema()->edgeInstance($name, $type, $fresh);
    }

    /**
     * Extract Data Fetcher from IoC container.
     *
     * @param  string $name
     * @return \Nuwave\Lighthouse\Support\DataLoader\GraphQLDataFetcher
     */
    public function dataFetcher($name)
    {
        return $this->schema()->dataFetcherInstance($name);
    }

    /**
     * Extract Data Loader from IoC container.
     *
     * @param  string $name
     * @return \Nuwave\Lighthouse\Support\DataLoader\GraphQLDataLoader
     */
    public function dataLoader($name)
    {
        return $this->schema()->dataLoaderInstance($name);
    }

    /**
     * Get cursor encoder for connection edge.
     *
     * @param  string $name
     * @return Closure
     */
    public function cursorEncoder($name)
    {
        return $this->schema()->encoder($name);
    }

    /**
     * Get cursor decoder for connection edge.
     *
     * @param  string $name
     * @return Closure
     */
    public function cursorDecoder($name)
    {
        return $this->schema()->decoder($name);
    }

    /**
     * Get collection of registered types.
     *
     * @return \Illuminate\Support\Collection
     */
    public function types()
    {
        return new Collection($this->schema()->getTypeRegistrar()->all());
    }

    /**
     * Get collection of registered queries.
     *
     * @return \Illuminate\Support\Collection
     */
    public function queries()
    {
        return new Collection($this->schema()->getQueryRegistrar()->all());
    }

    /**
     * Get collection of registered subscriptions.
     *
     * @return \Illuminate\Support\Collection
     */
    public function subscriptions()
    {
        return new Collection($this->schema()->getSubscriptionRegistrar()->all());
    }

    /**
     * Get collection of registered mutations.
     *
     * @return \Illuminate\Support\Collection
     */
    public function mutations()
    {
        return new Collection($this->schema()->getMutationRegistrar()->all());
    }

    /**
     * Get collection of registered connections.
     *
     * @return \Illuminate\Support\Collection
     */
    public function connections()
    {
        return new Collection($this->schema()->getConnectionRegistrar()->all());
    }

    /**
     * Get list of available connections to eager load.
     *
     * @param  int $depth
     * @return array
     */
    public function eagerLoad($depth = null)
    {
        $collection = $this->parser()->connections()->pluck('path');

        if ($depth !== null) {
            $depth = (int) $depth;
            $collection = $collection->filter(function ($path) use ($depth) {
                return count(explode('.', $path)) <= $depth;
            });
        }

        return $collection->toArray();
    }

    /**
     * Set local instance of schema.
     *
     * @param \Nuwave\Lighthouse\Schema\SchemaBuilder $schema
     */
    public function setSchema(SchemaBuilder $schema)
    {
        $this->schema = $schema;
    }

    /**
     * Get instance of schema builder.
     *
     * @return \Nuwave\Lighthouse\Schema\SchemaBuilder
     */
    public function schema()
    {
        if (! $this->schema) {
            $this->schema = app(SchemaBuilder::class);
        }

        return $this->schema;
    }

    /**
     * Set local instance of cache.
     *
     * @param \Nuwave\Lighthouse\Support\Cache\FileStore $cache
     */
    public function setCache(FileStore $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Get instance of cache.
     *
     * @return \Nuwave\Lighthouse\Support\Cache\FileStore
     */
    public function cache()
    {
        return $this->cache ?: app(FileStore::class);
    }

    /**
     * Get instance of query parser.
     *
     * @return \Nuwave\Lighthouse\Schema\QueryParser
     */
    public function parser()
    {
        return new QueryParser($this->schema(), $this->query);
    }

    /**
     * Resolve instance of field parser.
     *
     * @return \Nuwave\Lighthouse\Schema\FieldParser
     */
    public function fieldParser()
    {
        return app(FieldParser::class);
    }
}
