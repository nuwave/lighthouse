<?php

namespace Nuwave\Lighthouse\Pagination;

use Illuminate\Support\Collection;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ConnectionField
{
    /**
     * Resolve page info for connection.
     *
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator  $paginator
     * @return array
     */
    public function pageInfoResolver(LengthAwarePaginator $paginator): array
    {
        return [
            'total' => $paginator->total(),
            'count' => $paginator->count(),
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
            'hasNextPage' => $paginator->hasMorePages(),
            'hasPreviousPage' => $paginator->currentPage() > 1,
            'startCursor' => $paginator->firstItem()
                ? Cursor::encode($paginator->firstItem())
                : null,
            'endCursor' => $paginator->lastItem()
                ? Cursor::encode($paginator->lastItem())
                : null,
        ];
    }

    /**
     * Resolve edges for connection.
     *
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator  $paginator
     * @param  array  $args
     * @param  \Nuwave\Lighthouse\Support\Contracts\GraphQLContext  $context
     * @param  GraphQL\Type\Definition\ResolveInfo  $resolveInfo
     * @return \Illuminate\Support\Collection
     */
    public function edgeResolver(LengthAwarePaginator $paginator, $args, GraphQLContext $context, ResolveInfo $resolveInfo): Collection
    {
        $returnTypeFields = $resolveInfo
            ->returnType
            ->ofType
            ->getFields();
        $firstItem = $paginator->firstItem();

        return $paginator
            ->values()
            ->map(function ($item, $index) use ($firstItem, $returnTypeFields): array {
                $data = [];

                foreach ($returnTypeFields as $field) {
                    switch ($field->name) {
                        case 'cursor':
                            $data['cursor'] = Cursor::encode($firstItem + $index);
                            break;

                        case 'node':
                            $data['node'] = $item;
                            break;

                        default:
                            // All other fields on the return type are assumed to be part
                            // of the edge, so we try to locate them in the pivot attribute
                            if (isset($item->pivot->{$field->name})) {
                                $data[$field->name] = $item->pivot->{$field->name};
                            }
                    }
                }

                return $data;
            });
    }
}
