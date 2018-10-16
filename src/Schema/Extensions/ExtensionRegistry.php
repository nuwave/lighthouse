<?php

namespace Nuwave\Lighthouse\Schema\Extensions;

use Illuminate\Support\Collection;
use GraphQL\Executor\ExecutionResult;
use Nuwave\Lighthouse\Support\Pipeline;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;

class ExtensionRegistry implements \JsonSerializable
{
    /** @var Pipeline */
    protected $pipeline;

    /** @var Collection|GraphQLExtension[] */
    protected $extensions;

    /**
     * @param Pipeline $pipeline
     */
    public function __construct(Pipeline $pipeline)
    {
        $this->pipeline = $pipeline;

        $this->extensions = collect(
            config('lighthouse.extensions', [])
        )->mapWithKeys(function (string $extension) {
            $extensionInstance = resolve($extension);

            if (! $extensionInstance instanceof GraphQLExtension) {
                throw new \Exception(sprintf(
                    'The class [%s] was registered as an extensions but is not an instanceof %s',
                    $extension,
                    GraphQLExtension:: class
                ));
            }

            return [$extensionInstance::name() => $extensionInstance];
        });
    }

    /**
     * Get registered extension by its short name.
     *
     * For example, retrieve the TracingExtension by calling $this->get('tracing')
     *
     * @param string $shortName
     *
     * @return GraphQLExtension|null
     */
    public function get(string $shortName)
    {
        return $this->extensions->get($shortName);
    }

    /**
     * Notify all registered extensions that a request did start.
     *
     * @param ExtensionRequest $request
     *
     * @return ExtensionRegistry
     */
    public function requestDidStart(ExtensionRequest $request): ExtensionRegistry
    {
        $this->extensions->each(function (GraphQLExtension $extension) use ($request) {
            $extension->requestDidStart($request);
        });

        return $this;
    }

    /**
     * Notify all registered extensions that a batched query did start.
     *
     * @param int index
     *
     * @return ExtensionRegistry
     */
    public function batchedQueryDidStart($index)
    {
        $this->extensions->each(function (GraphQLExtension $extension) use ($index) {
            $extension->batchedQueryDidStart($index);
        });

        return $this;
    }

    /**
     * Notify all registered extensions that a batched query did end.
     *
     * @param ExecutionResult $result
     * @param int             $index
     *
     * @return ExtensionRegistry
     */
    public function batchedQueryDidEnd(ExecutionResult $result, $index)
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
     * @param array $response
     *
     * @return array
     */
    public function willSendResponse(array $response)
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
     * Allow Extensions to manipulate the Schema.
     *
     * @param DocumentAST $documentAST
     *
     * @return DocumentAST
     */
    public function manipulate(DocumentAST $documentAST): DocumentAST
    {
        return $this->extensions
            ->reduce(
                function (DocumentAST $documentAST, GraphQLExtension $extension) {
                    return $extension->manipulateSchema($documentAST);
                },
                $documentAST
            );
    }

    /**
     * Render the result of the extensions to an array that is put in the response.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->extensions->jsonSerialize();
    }
}
