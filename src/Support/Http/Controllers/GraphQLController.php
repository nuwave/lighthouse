<?php

namespace Nuwave\Lighthouse\Support\Http\Controllers;

use GraphQL\Server\RequestError;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Http\Request;
use Laragraph\Utils\RequestParser;
use Nuwave\Lighthouse\Events\EndRequest;
use Nuwave\Lighthouse\Events\StartRequest;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Support\Contracts\CreatesResponse;
use Safe\Exceptions\JsonException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class GraphQLController
{
    public function __invoke(
        Request $request,
        GraphQL $graphQL,
        EventsDispatcher $eventsDispatcher,
        RequestParser $requestParser,
        CreatesResponse $createsResponse,
        CreatesContext $createsContext
    ): Response {
        $eventsDispatcher->dispatch(
            new StartRequest($request)
        );

        try {
            $operationOrOperations = $requestParser->parseRequest($request);
        } catch (JsonException|RequestError $e) {
            throw new BadRequestHttpException('Error parsing GraphQL request', $e);
        }

        $context = $createsContext->generate($request);

        $result = $graphQL->executeOperationOrOperations($operationOrOperations, $context);

        $response = $createsResponse->createResponse($result);

        $eventsDispatcher->dispatch(
            new EndRequest($response)
        );

        return $response;
    }
}
