<?php

namespace Nuwave\Lighthouse\Execution;

use GraphQL\Error\InvariantViolation;
use GraphQL\Server\Helper;
use GraphQL\Server\OperationParams;
use GraphQL\Server\RequestError;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class LighthouseController
{
    /**
     * @var \GraphQL\Server\Helper
     */
    protected $helper;

    public function __construct()
    {
        $this->helper = new Helper();
    }

    public function __invoke(Request $request)
    {
        $operationParams = $this->parseRequest($request);
    }

    /**
     * Converts an incoming HTTP request to one or more OperationParams.
     *
     * @return OperationParams[]|OperationParams
     *
     * @throws RequestError
     */
    protected function parseRequest(Request $request)
    {
        if ($request->isMethod('GET')) {
            $bodyParams = [];
        } else {
            $contentType = $request->header('content-type');

            if (empty($contentType)) {
                throw new RequestError('Missing "Content-Type" header.');
            }

            if ($contentType === 'application/graphql') {
                $bodyParams = ['query' => $request->getContent()];
            } elseif ($contentType === 'multipart/form-data') {
                $bodyParams = $this->inlineFiles($request);
            } else {
                // In all other cases, we assume we are given JSON encoded input
                $bodyParams = \Safe\json_decode($request->getContent(), true);
            }
        }

        return $this->helper->parseRequestParams(
            $request->getMethod(),
            $bodyParams,
            $request->query()
        );
    }

    /**
     * Inline file uploads given through a multipart request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed[]
     */
    protected function inlineFiles(Request $request): array
    {
        $jsonInput = \Safe\json_decode($request->getContent(), true);

        if (! isset($jsonInput['map'])) {
            throw new InvariantViolation(
                'Could not find a valid map, be sure to conform to GraphQL multipart request specification: https://github.com/jaydenseric/graphql-multipart-request-spec'
            );
        }

        $bodyParams = $jsonInput['operations'];

        /**
         * @var string
         * @var string[] $operationsPaths
         */
        foreach ($jsonInput['map'] as $fileKey => $operationsPaths) {
            $file = $request->file($fileKey);

            /** @var string $operationsPath */
            foreach ($operationsPaths as $operationsPath) {
                Arr::set($operations, $operationsPath, $file);
            }
        }

        return $bodyParams;
    }
}
