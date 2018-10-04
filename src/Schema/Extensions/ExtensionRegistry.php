<?php

namespace Nuwave\Lighthouse\Schema\Extensions;

use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;

class ExtensionRegistry implements \JsonSerializable
{
    /**
     * @var Collection|GraphQLExtension[]
     */
    protected $extensions;

    public function __construct()
    {
        $this->extensions = collect(
            config('lighthouse.extensions', [])
        )->mapWithKeys(function(string $extension){
            $extensionInstance = resolve($extension);

            if(!$extensionInstance instanceof GraphQLExtension){
                throw new \Exception("The class [$extension] was registered as an extensions but is not an instanceof \Nuwave\Lighthouse\Schema\Extensions\GraphQLExtension");
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
                function(DocumentAST $documentAST, GraphQLExtension $extension){
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
