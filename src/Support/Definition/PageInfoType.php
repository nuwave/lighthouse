<?php

namespace Nuwave\Lighthouse\Support\Definition;

use GraphQL\Type\Definition\Type;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PageInfoType extends GraphQLType
{
    /**
     * Attributes of PageInfo.
     *
     * @var array
     */
    protected $attributes = [
        'name' => 'PageInfo',
        'description' => 'Information to aid in pagination.',
    ];

    /**
     * Fields available on PageInfo.
     *
     * @return array
     */
    public function fields()
    {
        return [
            'hasNextPage' => [
                'type' => Type::nonNull(Type::boolean()),
                'description' => 'When paginating forwards, are there more items?',
                'resolve' => function ($collection) {
                    if ($collection instanceof LengthAwarePaginator) {
                        return $collection->hasMorePages();
                    }

                    return false;
                },
            ],
            'hasPreviousPage' => [
                'type' => Type::nonNull(Type::boolean()),
                'description' => 'When paginating backwards, are there more items?',
                'resolve' => function ($collection) {
                    if ($collection instanceof LengthAwarePaginator) {
                        return $collection->currentPage() > 1;
                    }

                    return false;
                },
            ],
            'startCursor' => [
                'type' => Type::string(),
                'description' => 'When paginating backwards, the cursor to continue.',
                'resolve' => function ($collection) {
                    if ($collection instanceof LengthAwarePaginator) {
                        return $this->encodeGlobalId(
                            'arrayconnection',
                            $collection->firstItem() * $collection->currentPage()
                        );
                    }

                    return;
                },
            ],
            'endCursor' => [
                'type' => Type::string(),
                'description' => 'When paginating forwards, the cursor to continue.',
                'resolve' => function ($collection) {
                    if ($collection instanceof LengthAwarePaginator) {
                        return $this->encodeGlobalId(
                            'arrayconnection',
                            $collection->lastItem() * $collection->currentPage()
                        );
                    }

                    return;
                },
            ],
            'total' => [
                'type' => Type::int(),
                'description' => 'Total number of node in connection.',
                'resolve' => function ($collection) {
                    if ($collection instanceof LengthAwarePaginator) {
                        return $collection->total();
                    }

                    return;
                },
            ],
            'count' => [
                'type' => Type::int(),
                'description' => 'Count of nodes in current request.',
                'resolve' => function ($collection) {
                    if ($collection instanceof LengthAwarePaginator) {
                        return $collection->count();
                    }

                    return;
                },
            ],
            'currentPage' => [
                'type' => Type::int(),
                'description' => 'Current page of request.',
                'resolve' => function ($collection) {
                    if ($collection instanceof LengthAwarePaginator) {
                        return $collection->currentPage();
                    }

                    return;
                },
            ],
            'lastPage' => [
                'type' => Type::int(),
                'description' => 'Last page in connection.',
                'resolve' => function ($collection) {
                    if ($collection instanceof LengthAwarePaginator) {
                        return $collection->lastPage();
                    }

                    return;
                },
            ],
        ];
    }
}
