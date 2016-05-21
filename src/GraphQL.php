<?php

namespace Nuwave\Relay;

use GraphQL\Schema;
use GraphQL\Type\Definition\ObjectType;
use Illuminate\Support\Collection;
use Nuwave\Relay\Support\Traits\Container\TypeRegistrar;
use Nuwave\Relay\Support\Traits\Container\QueryRegistrar;
use Nuwave\Relay\Support\Traits\Container\MutationRegistrar;

class GraphQL
{
    use TypeRegistrar,
        QueryRegistrar,
        MutationRegistrar;

    /**
     * Instance of application.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * Create new instance of graphql container.
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Generate GraphQL Schema.
     *
     * @return \GraphQL\Schema
     */
    public function schema()
    {
        $queryType = $this->generateSchemaType($this->getQueries(), 'Query');
        $mutationType = $this->generateSchemaType($this->getMutations(), 'Mutation');

        return new Schema($queryType, $mutationType);
    }

    /**
     * Generate type from collection of fields.
     *
     * @param  Collection $fields
     * @param  array     $options
     * @return \GraphQL\Type\Definition\ObjectType
     */
    protected function generateSchemaType(Collection $fields, $name)
    {
        $config = [
            'name' => $name,
            'fields' => []
        ];

        return $fields->transform(function ($field) {
            return is_string($field) ? app($field)->toArray() : $field;
        })->reduce(function ($objectType, $field) {
            $objectType->config['fields'] = array_merge($objectType->getFields(), [$field]);

            return $objectType;
        }, new ObjectType($config));
    }
}
