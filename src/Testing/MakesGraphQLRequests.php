<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Testing;

use GraphQL\Type\Introspection;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use Nuwave\Lighthouse\Http\Responses\MemoryStream;
use Nuwave\Lighthouse\Subscriptions\Broadcasters\LogBroadcaster;
use Nuwave\Lighthouse\Subscriptions\BroadcastManager;
use Nuwave\Lighthouse\Subscriptions\Contracts\Broadcaster;
use Nuwave\Lighthouse\Support\Contracts\CanStreamResponse;
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
     */
    protected TestResponse $introspectionResult;

    /** Used to test deferred queries. */
    protected MemoryStream $deferStream;

    /**
     * Execute a GraphQL operation as if it was sent as a request to the server.
     *
     * @param  string  $query  The GraphQL operation to send
     * @param  array<string, mixed>  $variables  The variables to include in the query
     * @param  array<string, mixed>  $extraParams  Extra parameters to add to the JSON payload
     * @param  array<string, mixed>  $headers  HTTP headers to pass to the POST request
     * @param  array<string, string>  $routeParams  Parameters to pass to the route
     */
    protected function graphQL(
        string $query,
        array $variables = [],
        array $extraParams = [],
        array $headers = [],
        array $routeParams = [],
    ): TestResponse {
        $params = ['query' => $query];

        if ($variables !== []) {
            $params += ['variables' => $variables];
        }

        $params += $extraParams;

        return $this->postGraphQL($params, $headers, $routeParams);
    }

    /**
     * Send a POST request to the GraphQL endpoint.
     *
     * Use this over graphQL() when you need more control or want to
     * test how your server behaves on incorrect inputs.
     *
     * @param  array<mixed, mixed>  $data  JSON-serializable payload
     * @param  array<string, string>  $headers  HTTP headers to pass to the POST request
     * @param  array<string, string>  $routeParams  Parameters to pass to the route
     */
    protected function postGraphQL(array $data, array $headers = [], array $routeParams = []): TestResponse
    {
        return $this->postJson(
            $this->graphQLEndpointUrl($routeParams),
            $data,
            $headers,
        );
    }

    /**
     * Send a multipart form request to the GraphQL endpoint.
     *
     * This is used for file uploads conforming to the specification:
     * https://github.com/jaydenseric/graphql-multipart-request-spec
     *
     * @param  array<string, mixed>|array<int, array<string, mixed>>  $operations
     * @param  array<array<int, string>>  $map
     * @param  array<\Illuminate\Http\UploadedFile>|array<array<mixed>>  $files
     * @param  array<string, string>  $headers  Will be merged with Content-Type: multipart/form-data
     * @param  array<string, string>  $routeParams  Parameters to pass to the route
     */
    protected function multipartGraphQL(
        array $operations,
        array $map,
        array $files,
        array $headers = [],
        array $routeParams = [],
    ): TestResponse {
        $parameters = [
            'operations' => \Safe\json_encode($operations),
            'map' => \Safe\json_encode($map),
        ];

        return $this->call(
            'POST',
            $this->graphQLEndpointUrl($routeParams),
            $parameters,
            [],
            $files,
            $this->transformHeadersToServerVars(array_merge(
                [
                    'Content-Type' => 'multipart/form-data',
                ],
                $headers,
            )),
        );
    }

    /**
     * Send the introspection query to the GraphQL server.
     *
     * Returns the cached first result on repeated calls.
     */
    protected function introspect(): TestResponse
    {
        return $this->introspectionResult
            ??= $this->graphQL(Introspection::getIntrospectionQuery());
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
        return Arr::first(
            $this->introspect()->json($path),
            static fn (array $result): bool => $result['name'] === $name,
        );
    }

    /**
     * Return the full URL to the GraphQL endpoint.
     *
     * @param  array<string, string>  $routeParams  Parameters to pass to the route
     */
    protected function graphQLEndpointUrl(array $routeParams = []): string
    {
        $config = Container::getInstance()->make(ConfigRepository::class);
        $routeName = $config->get('lighthouse.route.name');

        return route($routeName, $routeParams);
    }

    /**
     * Send the query and capture all chunks of the streamed response.
     *
     * @param  string  $query  The GraphQL query to send
     * @param  array<string, mixed>  $variables  The variables to include in the query
     * @param  array<string, mixed>  $extraParams  Extra parameters to add to the HTTP payload
     * @param  array<string, mixed>  $headers  HTTP headers to pass to the POST request
     *
     * @return array<int, mixed> The chunked results
     */
    protected function streamGraphQL(
        string $query,
        array $variables = [],
        array $extraParams = [],
        array $headers = [],
    ): array {
        if (! isset($this->deferStream)) {
            $this->setUpDeferStream();
        }

        $response = $this->graphQL($query, $variables, $extraParams, $headers);

        /** @var mixed $baseResponse Laravel type hint is wrong */
        $baseResponse = $response->baseResponse;
        if (! $baseResponse instanceof StreamedResponse) {
            Assert::fail('Expected the response to be a streamed response but got a regular response.');
        }

        $baseResponse->send();

        return $this->deferStream->chunks;
    }

    /** Set up the stream to make queries with `@defer`. */
    protected function setUpDeferStream(): void
    {
        $this->deferStream = new MemoryStream();

        Container::getInstance()->singleton(
            CanStreamResponse::class,
            fn (): MemoryStream => $this->deferStream,
        );
    }

    /** Configure an error handler that rethrows all errors passed to it. */
    protected function rethrowGraphQLErrors(): void
    {
        $config = Container::getInstance()->make(ConfigRepository::class);
        $config->set('lighthouse.error_handlers', [RethrowingErrorHandler::class]);
    }

    /**
     * @deprecated use TestsSubscriptions
     * TODO remove in the next major version
     */
    protected function setUpSubscriptionEnvironment(): void
    {
        $app = Container::getInstance();

        $config = $app->make(ConfigRepository::class);
        $config->set('lighthouse.subscriptions.queue_broadcasts', false);
        $config->set('lighthouse.subscriptions.storage', 'array');
        $config->set('lighthouse.subscriptions.storage_ttl', null);

        // binding an instance to the container, so it can be spied on
        $app->bind(Broadcaster::class, static fn (ConfigRepository $config): \Nuwave\Lighthouse\Subscriptions\Broadcasters\LogBroadcaster => new LogBroadcaster(
            $config->get('lighthouse.subscriptions.broadcasters.log'),
        ));

        $broadcastManager = $app->make(BroadcastManager::class);
        assert($broadcastManager instanceof BroadcastManager);

        // adding a custom driver which is a spied version of log driver
        $broadcastManager->extend('mock', fn () => $this->spy(LogBroadcaster::class)->makePartial());

        // set the custom driver as the default driver
        $config->set('lighthouse.subscriptions.broadcaster', 'mock');
    }
}
