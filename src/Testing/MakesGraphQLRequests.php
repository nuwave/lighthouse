<?php

namespace Nuwave\Lighthouse\Testing;

use Illuminate\Foundation\Testing\TestResponse;

/**
 * Useful helpers for PHPUnit testing.
 *
 * It depends upon methods defined in
 * @see \Illuminate\Foundation\Testing\Concerns\MakesHttpRequests
 */
trait MakesGraphQLRequests
{
    /**
     * Visit the given URI with a POST request, expecting a JSON response.
     *
     * @param  string  $uri
     * @param  array  $data
     * @param  array  $headers
     * @return \Illuminate\Foundation\Testing\TestResponse
     */
    abstract public function postJson($uri, array $data = [], array $headers = []);

    /**
     * Call the given URI and return the Response.
     *
     * @param  string  $method
     * @param  string  $uri
     * @param  array  $parameters
     * @param  array  $cookies
     * @param  array  $files
     * @param  array  $server
     * @param  string  $content
     * @return \Illuminate\Foundation\Testing\TestResponse
     */
    abstract public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null);

    /**
     * Transform headers array to array of $_SERVER vars with HTTP_* format.
     *
     * @param  array  $headers
     * @return array
     */
    abstract protected function transformHeadersToServerVars(array $headers);

    /**
     * Execute a query as if it was sent as a request to the server.
     *
     * @param  string  $query
     * @return \Illuminate\Foundation\Testing\TestResponse
     */
    protected function graphQL(string $query): TestResponse
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
     * @return \Illuminate\Foundation\Testing\TestResponse
     */
    protected function postGraphQL(array $data, array $headers = []): TestResponse
    {
        return $this->postJson(
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
     * @return \Illuminate\Foundation\Testing\TestResponse
     */
    protected function multipartGraphQL(array $parameters, array $files): TestResponse
    {
        return $this->call(
            'POST',
            $this->graphQLEndpointUrl(),
            $parameters,
            [],
            $files,
            $this->transformHeadersToServerVars([
                'Content-Type' => 'multipart/form-data',
            ])
        );
    }

    /**
     * Return the full URL to the GraphQL endpoint.
     *
     * @return string
     */
    private function graphQLEndpointUrl(): string
    {
        $path = config('lighthouse.route_name');

        if ($prefix = config('lighthouse.route.prefix')) {
            $path = $prefix.'/'.$path;
        }

        return $path;
    }
}
