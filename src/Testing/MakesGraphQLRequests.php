<?php

namespace Nuwave\Lighthouse\Testing;

use GraphQL\Type\Introspection;
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
     * Execute the introspection query on the GraphQL server.
     *
     * @return \Illuminate\Foundation\Testing\TestResponse
     */
    protected function introspect(): TestResponse
    {
        return $this->graphQL(Introspection::getIntrospectionQuery());
    }

    /**
     * Return the full introspection query.
     *
     * @see https://gist.github.com/craigbeck/b90915d49fda19d5b2b17ead14dcd6da
     *
     * @return string
     */
    public static function introspectionQuery(): string
    {
        return /* @lang GraphQL */
    <<<'GRAPHQL'
  query IntrospectionQuery {
    __schema {
      queryType { name }
      mutationType { name }
      subscriptionType { name }
      types {
        ...FullType
      }
      directives {
        name
        description
        args {
          ...InputValue
        }
        locations
      }
    }
  }

  fragment FullType on __Type {
    kind
    name
    description
    fields(includeDeprecated: true) {
      name
      description
      args {
        ...InputValue
      }
      type {
        ...TypeRef
      }
      isDeprecated
      deprecationReason
    }
    inputFields {
      ...InputValue
    }
    interfaces {
      ...TypeRef
    }
    enumValues(includeDeprecated: true) {
      name
      description
      isDeprecated
      deprecationReason
    }
    possibleTypes {
      ...TypeRef
    }
  }

  fragment InputValue on __InputValue {
    name
    description
    type { ...TypeRef }
    defaultValue
  }

  fragment TypeRef on __Type {
    kind
    name
    ofType {
      kind
      name
      ofType {
        kind
        name
        ofType {
          kind
          name
        }
      }
    }
  }
GRAPHQL;
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
