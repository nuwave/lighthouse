<?php

namespace Nuwave\Lighthouse\Support\Http\Controllers;

use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Http\Request;
use Laragraph\Utils\RequestParser;
use Nuwave\Lighthouse\CacheControl\CacheControl;
use Nuwave\Lighthouse\Events\EndRequest;
use Nuwave\Lighthouse\Events\StartRequest;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Support\Contracts\CreatesResponse;
use Symfony\Component\HttpFoundation\Response;

class GraphQLController
{
    public function __invoke(
        Request $request,
        GraphQL $graphQL,
        EventsDispatcher $eventsDispatcher,
        RequestParser $requestParser,
        CreatesResponse $createsResponse,
        CreatesContext $createsContext,
        CacheControl $cacheControl
    ): Response {
        $eventsDispatcher->dispatch(
            new StartRequest($request)
        );

        $operationOrOperations = $requestParser->parseRequest($request);
        $context = $createsContext->generate($request);

        $result = $graphQL->executeOperationOrOperations($operationOrOperations, $context);

        $response = $createsResponse->createResponse($result);
        $response = $response->setCache($cacheControl->makeHeaderOptions());

        $eventsDispatcher->dispatch(
            new EndRequest($response)
        );

        app()->forgetInstance(CacheControl::class);

        return $response;
    }
}
