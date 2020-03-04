<?php

namespace Nuwave\Lighthouse\Testing;

use GraphQL\Type\Introspection;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Support\Contracts\CanStreamResponse;
use Nuwave\Lighthouse\Support\Http\Responses\MemoryStream;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
     * @var \Illuminate\Http\Response|null
     */
    protected $introspectionResult;

    /**
     * Used to test deferred queries.
     *
     * @var \Nuwave\Lighthouse\Support\Http\Responses\MemoryStream|null
     */
    protected $deferStream;

    /**
     * Execute a query as if it was sent as a request to the server.
     *
     * @param  string  $query
     * @param  array|null  $variables
     * @param  array  $extraParams
     * @return $this
     */
    protected function graphQL(string $query, array $variables = null, array $extraParams = []): self
    {
        $params = ['query' => $query];

        if ($variables) {
            $params += ['variables' => $variables];
        }

        $params += $extraParams;
        $this->postGraphQL($params);

        return $this;
    }

    /**
     * Execute a query as if it was sent as a request to the server.
     *
     * @param  mixed[]  $data
     * @param  mixed[]  $headers
     * @return $this
     */
    protected function postGraphQL(array $data, array $headers = []): self
    {
        $this->post(
            $this->graphQLEndpointUrl(),
            $data,
            $headers
        );

        return $this;
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
    protected function multipartGraphQL(array $parameters, array $files): self
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
    protected function introspect(): self
    {
        if ($this->introspectionResult) {
            return $this;
        }

        $this->graphQL(Introspection::getIntrospectionQuery());
        $this->introspectionResult = $this->response;

        return $this;
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
            json_decode($this->introspectionResult->getContent(), true),
            $path
        );

        return Arr::first(
            $results,
            static function (array $result) use ($name): bool {
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

    /**
     * Send the query and capture all chunks of the streamed response.
     *
     * @param  string  $query
     * @param  array|null  $variables
     * @param  array  $extraParams
     * @return array
     */
    protected function streamGraphQL(string $query, array $variables = null, array $extraParams = []): array
    {
        if ($this->deferStream === null) {
            $this->setUpDeferStream();
        }

        $response = $this->graphQL($query, $variables, $extraParams);

        if (! $response->response instanceof StreamedResponse) {
            Assert::fail('Expected the response to be a streamed response but got a regular response.');
        }

        $response->response->send();

        return $this->deferStream->chunks;
    }

    /**
     * Set up the stream to make queries with @defer.
     *
     * @return void
     */
    protected function setUpDeferStream(): void
    {
        $this->deferStream = new MemoryStream;

        app()->singleton(CanStreamResponse::class, function (): MemoryStream {
            return $this->deferStream;
        });
    }
}
