<?php

namespace Nuwave\Lighthouse\Support\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @deprecated will be removed in v4
 */
class GraphQLFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'graphql';
    }
}
