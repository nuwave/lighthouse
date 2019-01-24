<?php

namespace Nuwave\Lighthouse\Support\Http\Responses;

use Nuwave\Lighthouse\Defer\Defer;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry;
use Nuwave\Lighthouse\Support\Contracts\GraphQLResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class Response implements GraphQLResponse
{
    /**
     * @var \Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry
     */
    protected $extensionRegistry;

    /**
     * @param  \Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry  $extensionRegistry
     * @return void
     */
    public function __construct(ExtensionRegistry $extensionRegistry)
    {
        $this->extensionRegistry = $extensionRegistry;
    }

    /**
     * Create GraphQL response.
     *
     * @param  array  $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function create(array $data): SymfonyResponse
    {
        /** @var \Nuwave\Lighthouse\Defer\Defer|null $deferExtension */
        if ($deferExtension = $this->extensionRegistry->get(Defer::name())) {
            return $deferExtension->response($data);
        }

        return response($data);
    }
}
