<?php

namespace Nuwave\Lighthouse\Testing;

use GraphQL\Type\Introspection;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Support\Contracts\CanStreamResponse;
use Nuwave\Lighthouse\Support\Http\Responses\MemoryStream;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Testing helpers for making requests to the GraphQL endpoint.
 *
 * @mixin \Illuminate\Foundation\Testing\Concerns\MakesHttpRequests
 */
trait MakesGraphQLRequests
{
    /**
     * Stores the result of the introspection query.
     *
     * On the first call to introspect() this property is set to
     * cache the result, as introspection is quite expensive.
     *
     * @var \Illuminate\Testing\TestResponse|null
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
     * @param  string  $query  The GraphQL query to send
     * @param  array<string, mixed>  $variables  The variables to include in the query
     * @param  array<string, mixed>  $extraParams  Extra parameters to add to the JSON payload
     * @return \Illuminate\Testing\TestResponse
     */
    protected function graphQL(string $query, array $variables = [], array $extraParams = [])
    {
        $params = ['query' => $query];

        if ($variables) {
            $params += ['variables' => $variables];
        }

        $params += $extraParams;

        return $this->postGraphQL($params);
    }

    /**
     * Execute a POST to the GraphQL endpoint.
     *
     * Use this over graphQL() when you need more control or want to
     * test how your server behaves on incorrect inputs.
     *
     * @param  array<mixed, mixed>  $data
     * @param  array<string, string>  $headers
     * @return \Illuminate\Testing\TestResponse
     */
    protected function postGraphQL(array $data, array $headers = [])
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
     * @param  array<string, mixed>  $parameters
     * @param  array<int, \Illuminate\Http\Testing\File>  $files
     * @param  array<string, string>  $headers  Will be merged with Content-Type: multipart/form-data
     * @return \Illuminate\Testing\TestResponse
     */
    protected function multipartGraphQL(array $parameters, array $files, array $headers = [])
    {
        return $this->call(
            'POST',
            $this->graphQLEndpointUrl(),
            $parameters,
            [],
            $files,
            $this->transformHeadersToServerVars(array_merge(
                [
                    'Content-Type' => 'multipart/form-data',
                ],
                $headers
            ))
        );
    }

    /**
     * Execute the introspection query on the GraphQL server.
     *
     * @return \Illuminate\Testing\TestResponse
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
     * @return array<string, mixed>|null
     */
    protected function introspectType(string $name): ?array
    {
        return $this->introspectByName('data.__schema.types', $name);
    }

    /**
     * Run introspection and return a directive by name, if present.
     *
     * @return array<string, mixed>|null
     */
    protected function introspectDirective(string $name): ?array
    {
        return $this->introspectByName('data.__schema.directives', $name);
    }

    /**
     * Run introspection and return a result from the given path by name, if present.
     *
     * @return array<string, mixed>|null
     */
    protected function introspectByName(string $path, string $name): ?array
    {
        if (! $this->introspectionResult) {
            $this->introspect();
        }

        // TODO Replace with ->json() once we remove support for Laravel 5.5
        $results = data_get(
            $this->introspectionResult->decodeResponseJson(),
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
     */
    protected function graphQLEndpointUrl(): string
    {
        return route(config('lighthouse.route.name'));
    }

    /**
     * Send the query and capture all chunks of the streamed response.
     *
     * @param  string  $query  The GraphQL query to send
     * @param  array<string, mixed>  $variables  The variables to include in the query
     * @param  array<string, mixed>  $extraParams  Extra parameters to add to the HTTP payload
     * @return array<int, mixed>  The chunked results
     */
    protected function streamGraphQL(string $query, array $variables = [], array $extraParams = []): array
    {
        if ($this->deferStream === null) {
            $this->setUpDeferStream();
        }

        $response = $this->graphQL($query, $variables, $extraParams);

        if (! $response->baseResponse instanceof StreamedResponse) {
            Assert::fail('Expected the response to be a streamed response but got a regular response.');
        }

        $response->send();

        return $this->deferStream->chunks;
    }

    /**
     * Set up the stream to make queries with @defer.
     */
    protected function setUpDeferStream(): void
    {
        $this->deferStream = new MemoryStream;

        app()->singleton(CanStreamResponse::class, function (): MemoryStream {
            return $this->deferStream;
        });
    }
}
