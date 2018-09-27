<?php

namespace Nuwave\Lighthouse\Events;

use Nuwave\Lighthouse\GraphQL;

class BuildingAST
{
    /**
     * The GraphQL instance.
     * @var GraphQL
     */
    public $graphql;

    /**
     * BuildingAST constructor.
     *
     * @param GraphQL $graphql
     */
    public function __construct(GraphQL $graphql)
    {
        $this->graphql = $graphql;
    }
}
