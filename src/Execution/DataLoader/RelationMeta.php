<?php

namespace Nuwave\Lighthouse\Execution\DataLoader;

class RelationMeta
{
    /**
     * A function called with the query builder when resolving the relation.
     *
     * @var \Closure
     */
    public $decorateBuilder;

    /**
     * Optionally, a relation may be paginated.
     *
     * @var \Nuwave\Lighthouse\Pagination\PaginationArgs|null
     */
    public $paginationArgs;
}
