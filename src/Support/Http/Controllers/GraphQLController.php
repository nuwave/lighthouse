<?php

namespace Nuwave\Lighthouse\Support\Http\Controllers;

use Nuwave\Lighthouse\GraphQL;
use Illuminate\Routing\Controller;
use Illuminate\Container\Container;
use Nuwave\Lighthouse\Events\StartRequest;
use Nuwave\Lighthouse\Execution\GraphQLRequest;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Support\Contracts\CreatesResponse;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;

class GraphQLController extends Controller
{
    /**
     * @var \Nuwave\Lighthouse\GraphQL
     */
    protected $graphQL;

    /**
     * @var \Nuwave\Lighthouse\Support\Contracts\CreatesContext
     */
    protected $createsContext;

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

    /**
     * Inject middleware into request.
     *
     * @param  \Nuwave\Lighthouse\GraphQL  $graphQL
     * @param  \Nuwave\Lighthouse\Support\Contracts\CreatesContext  $createsContext
     * @param  \Illuminate\Contracts\Events\Dispatcher  $eventsDispatcher
     * @param  \Nuwave\Lighthouse\Support\Contracts\CreatesResponse  $createsResponse
     * @param  \Illuminate\Container\Container  $container
     * @return void
     */
    public function __construct(
        GraphQL $graphQL,
        CreatesContext $createsContext,
        EventsDispatcher $eventsDispatcher,
        CreatesResponse $createsResponse,
        Container $container
    ) {
        $this->graphQL = $graphQL;
        $this->createsContext = $createsContext;
        $this->eventsDispatcher = $eventsDispatcher;
        $this->createsResponse = $createsResponse;
        $this->container = $container;
    }

    /**
     * Execute GraphQL query.
     *
     * @param  \Nuwave\Lighthouse\Execution\GraphQLRequest  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function query(GraphQLRequest $request)
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
     * @param  \Nuwave\Lighthouse\Execution\GraphQLRequest  $request
     * @return mixed[]
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
