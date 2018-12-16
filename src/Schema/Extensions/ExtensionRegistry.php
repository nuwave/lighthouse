<?php

namespace Nuwave\Lighthouse\Schema\Extensions;

use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Support\Pipeline;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Execution\GraphQLRequest;

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
            $extensionInstance = app($extension);

            if (! $extensionInstance instanceof GraphQLExtension) {
                throw new \Exception(
                    "The class [$extension] was registered as an extension but is not an instanceof ".GraphQLExtension:: class
                );
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
     * Check if extension is registered by its short name.
     *
     * @param string $shortName
     *
     * @return bool
     */
    public function has(string $shortName): bool
    {
        return $this->extensions->has($shortName);
    }

    /**
     * Notify all registered extensions that a request did start.
     *
     * @param GraphQLRequest $request
     *
     * @return ExtensionRegistry
     */
    public function start(GraphQLRequest $request): ExtensionRegistry
    {
        $this->extensions->each(
            function (GraphQLExtension $extension) use ($request) {
                $extension->start($request);
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
        return collect($this->extensions->jsonSerialize())
            ->reject(function ($output) {
                return empty($output);
            })
            ->toArray();
    }
}
