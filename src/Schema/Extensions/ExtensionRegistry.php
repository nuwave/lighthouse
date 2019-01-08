<?php

namespace Nuwave\Lighthouse\Schema\Extensions;

use GraphQL\Executor\ExecutionResult;
use Nuwave\Lighthouse\Support\Pipeline;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;

class ExtensionRegistry implements \JsonSerializable
{
    /**
     * @var \Nuwave\Lighthouse\Support\Pipeline
     */
    protected $pipeline;

    /**
     * @var \Illuminate\Support\Collection<\Nuwave\Lighthouse\Schema\Extensions\GraphQLExtension>
     */
    protected $extensions;

    /**
     * @param  \Nuwave\Lighthouse\Support\Pipeline  $pipeline
     * @return void
     */
    public function __construct(Pipeline $pipeline)
    {
        $this->pipeline = $pipeline;

        $this->extensions = collect(
            config('lighthouse.extensions', [])
        )->mapWithKeys(function (string $extension): array {
            $extensionInstance = app($extension);

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
     * @param  string  $shortName
     * @return \Nuwave\Lighthouse\Schema\Extensions\GraphQLExtension|null
     */
    public function get(string $shortName): ?GraphQLExtension
    {
        return $this->extensions->get($shortName);
    }

    /**
     * Check if extension is registered by its short name.
     *
     * @param  string  $shortName
     * @return bool
     */
    public function has(string $shortName): bool
    {
        return $this->extensions->has($shortName);
    }

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
     * Allow Extensions to manipulate the Schema.
     *
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $documentAST
     * @return \Nuwave\Lighthouse\Schema\AST\DocumentAST
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
        return collect($this->extensions->jsonSerialize())->reject(function ($output) {
            return empty($output);
        })->toArray();
    }
}
