<?php

namespace Nuwave\Lighthouse\Support\Http\Controllers;

use Nuwave\Lighthouse\Defer\Defer;
use Nuwave\Lighthouse\GraphQL;
use Illuminate\Routing\Controller;
use Nuwave\Lighthouse\Events\StartRequest;
use Nuwave\Lighthouse\Execution\GraphQLRequest;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Nuwave\Lighthouse\Support\Contracts\GraphQLResponse;

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
     * @var \Nuwave\Lighthouse\Defer\Defer
     */
    protected $defer;

    /**
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $eventsDispatcher;

    /**
     * @var \Nuwave\Lighthouse\Support\Contracts\GraphQLResponse
     */
    private $createsResponse;

    /**
     * Inject middleware into request.
     *
     * @param  \Nuwave\Lighthouse\GraphQL  $graphQL
     * @param  \Nuwave\Lighthouse\Support\Contracts\CreatesContext  $createsContext
     * @param  \Nuwave\Lighthouse\Defer\Defer  $defer
     * @param  \Illuminate\Contracts\Events\Dispatcher  $eventsDispatcher
     * @param  \Nuwave\Lighthouse\Support\Contracts\GraphQLResponse  $createsResponse
     * @return void
     */
    public function __construct(
        GraphQL $graphQL,
        CreatesContext $createsContext,
        Defer $defer,
        EventsDispatcher $eventsDispatcher,
        GraphQLResponse $createsResponse
    ) {
        $this->graphQL = $graphQL;
        $this->createsContext = $createsContext;
        $this->defer = $defer;
        $this->eventsDispatcher = $eventsDispatcher;
        $this->createsResponse = $createsResponse;
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
            new StartRequest()
        );

        $result = $request->isBatched()
            ? $this->executeBatched($request)
            : $this->graphQL->executeRequest($request);

        return $this->createsResponse->create($result);
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
