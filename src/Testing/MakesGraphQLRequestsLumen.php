<?php

namespace Nuwave\Lighthouse\Testing;

use GraphQL\Type\Introspection;
use Illuminate\Support\Arr;

/**
 * Useful helpers for PHPUnit testing.
 *
 * @mixin \Laravel\Lumen\Testing\Concerns\MakesHttpRequests
 */
trait MakesGraphQLRequestsLumen
{
    /**
     * Stores the result of the introspection query.
     *
     * On the first call to introspect() this property is set to
     * cache the result, as introspection is quite expensive.
     *
     * @var $this|null
     */
    protected $introspectionResult;

    /**
     * Execute a query as if it was sent as a request to the server.
     *
     * @param  string  $query
     * @return $this
     */
    protected function graphQL(string $query)
    {
        return $this->postGraphQL(
            [
                'query' => $query,
            ]
        );
    }

    /**
     * Execute a query as if it was sent as a request to the server.
     *
     * @param  mixed[]  $data
     * @param  mixed[]  $headers
     * @return $this
     */
    protected function postGraphQL(array $data, array $headers = [])
    {
        return $this->post(
            $this->graphQLEndpointUrl(),
            $data,
            $headers
        );
    }

    /**
     * Send a multipart form request to GraphQL.
     *
     * This is used for file uploads conforming to the specification:
     * https://github.com/jaydenseric/graphql-multipart-request-spec
     *
     * @param  mixed[]  $parameters
     * @param  mixed[]  $files
     * @return $this
     */
    protected function multipartGraphQL(array $parameters, array $files)
    {
        $this->call(
            'POST',
            $this->graphQLEndpointUrl(),
            $parameters,
            [],
            $files,
            $this->transformHeadersToServerVars([
                'Content-Type' => 'multipart/form-data',
            ])
        );

        return $this;
    }

    /**
     * Execute the introspection query on the GraphQL server.
     *
     * @return $this
     */
    protected function introspect()
    {
        if ($this->introspectionResult) {
            return $this->introspectionResult;
        }

        return $this->introspectionResult = $this->graphQL(Introspection::getIntrospectionQuery());
    }

    /**
     * Run introspection and return a type by name, if present.
     *
     * @param  string  $name
     * @return mixed[]|null
     */
    protected function introspectType(string $name): ?array
    {
        return $this->introspectByName('data.__schema.types', $name);
    }

    /**
     * Run introspection and return a directive by name, if present.
     *
     * @param  string  $name
     * @return mixed[]|null
     */
    protected function introspectDirective(string $name): ?array
    {
        return $this->introspectByName('data.__schema.directives', $name);
    }

    /**
     * Run introspection and return a result from the given path by name, if present.
     *
     * @param  string  $path
     * @param  string  $name
     * @return mixed[]|null
     */
    protected function introspectByName(string $path, string $name): ?array
    {
        if (! $this->introspectionResult) {
            $this->introspect();
        }

        $results = data_get(
            json_decode($this->introspectionResult->response->getContent(), true),
            $path
        );

        return Arr::first(
            $results,
            function (array $result) use ($name): bool {
                return $result['name'] === $name;
            }
        );
    }

    /**
     * Return the full URL to the GraphQL endpoint.
     *
     * @return string
     */
    protected function graphQLEndpointUrl(): string
    {
        return config('lighthouse.route.uri');
    }
}
