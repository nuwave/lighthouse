<?php

namespace Nuwave\Lighthouse\Support\Http\Controllers;

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Routing\Controller;
use Nuwave\Lighthouse\Events\StartRequest;
use Nuwave\Lighthouse\Execution\GraphQLRequest;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Support\Contracts\CreatesResponse;
use Symfony\Component\HttpFoundation\Response;

class GraphQLController extends Controller
{
    /**
     * @var \Nuwave\Lighthouse\GraphQL
     */
    protected $graphQL;

    /**
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $eventsDispatcher;

    /**
     * @var \Nuwave\Lighthouse\Support\Contracts\CreatesResponse
     */
    protected $createsResponse;

    /**
     * @var \Illuminate\Container\Container
     */
    protected $container;

    public function __construct(
        GraphQL $graphQL,
        EventsDispatcher $eventsDispatcher,
        CreatesResponse $createsResponse,
        Container $container
    ) {
        $this->graphQL = $graphQL;
        $this->eventsDispatcher = $eventsDispatcher;
        $this->createsResponse = $createsResponse;
        $this->container = $container;
    }

    /**
     * Execute GraphQL query.
     */
    public function query(GraphQLRequest $request): Response
    {
        $this->eventsDispatcher->dispatch(
            new StartRequest($request)
        );

        $result = $request->isBatched()
            ? $this->executeBatched($request)
            : $this->graphQL->executeRequest($request);

        $response = $this->createsResponse->createResponse($result);

        // When handling multiple requests during the application lifetime,
        // for example in tests, we need a new GraphQLRequest instance
        // for each HTTP request, so we forget the singleton here.
        $this->container->forgetInstance(GraphQLRequest::class);

        return $response;
    }

    /**
     * Loop through the individual batched queries and collect the results.
     *
     * @return array<int, mixed>
     */
    protected function executeBatched(GraphQLRequest $request): array
    {
        $results = [];
        do {
            $results[] = $this->graphQL->executeRequest($request);
        } while ($request->advanceBatchIndex());

        return $results;
    }
}
