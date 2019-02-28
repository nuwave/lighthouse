<?php

namespace Nuwave\Lighthouse\Schema\Extensions;

use JsonSerializable;
use Illuminate\Support\Collection;
use GraphQL\Executor\ExecutionResult;
use Nuwave\Lighthouse\Support\Pipeline;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;

class ExtensionRegistry implements JsonSerializable
{
    /**
     * @var \Nuwave\Lighthouse\Support\Pipeline
     */
    protected $pipeline;

    /**
     * Notify all registered extensions that a request did start.
     *
     * @param  \Nuwave\Lighthouse\Schema\Extensions\ExtensionRequest  $request
     * @return $this
     */
    public function requestDidStart(ExtensionRequest $request): self
    {
        $this->extensions->each(function (GraphQLExtension $extension) use ($request) {
            $extension->requestDidStart($request);
        });

        return $this;
    }

    /**
     * Notify all registered extensions that a batched query did start.
     *
     * @param  int  $index
     * @return $this
     */
    public function batchedQueryDidStart($index): self
    {
        $this->extensions->each(function (GraphQLExtension $extension) use ($index) {
            $extension->batchedQueryDidStart($index);
        });

        return $this;
    }

    /**
     * Notify all registered extensions that a batched query did end.
     *
     * @param  \GraphQL\Executor\ExecutionResult  $result
     * @param  int  $index
     * @return $this
     */
    public function batchedQueryDidEnd(ExecutionResult $result, $index): self
    {
        $this->extensions->each(
            function (GraphQLExtension $extension) use ($result, $index) {
                $extension->batchedQueryDidEnd($result, $index);
            }
        );

        return $this;
    }

    /**
     * Notify all registered extensions that the
     * response will be sent.
     *
     * @param  array  $response
     * @return array
     */
    public function willSendResponse(array $response): array
    {
        return $this->pipeline
            ->send($response)
            ->through($this->extensions)
            ->via('willSendResponse')
            ->then(function (array $response) {
                return $response;
            });
    }


    /**
     * Render the result of the extensions to an array that is put in the response.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return collect($this->extensions->jsonSerialize())->reject(function ($output) {
            return empty($output);
        })->toArray();
    }
}
