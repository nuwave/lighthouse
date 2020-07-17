<?php

namespace Nuwave\Lighthouse\Pagination;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class ConnectionField
{
    /**
     * Resolve page info for connection.
     *
     * @return array<string, mixed>
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
     * @param  \Illuminate\Pagination\LengthAwarePaginator<mixed>  $paginator
     * @param  array<string, mixed>  $args
     */
    public function edgeResolver(LengthAwarePaginator $paginator, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Collection
    {
        // We know this must be a list, as it is constructed this way during schema manipulation
        /** @var \GraphQL\Type\Definition\ListOfType $listOfType */
        $listOfType = $resolveInfo->returnType;

        // We also know this is one of those two return types
        /** @var \GraphQL\Type\Definition\ObjectType|\GraphQL\Type\Definition\InterfaceType $objectLikeType */
        $objectLikeType = $listOfType->ofType;
        $returnTypeFields = $objectLikeType->getFields();

        $firstItem = $paginator->firstItem();

        return $paginator
            ->values()
            ->map(function ($item, int $index) use ($returnTypeFields, $firstItem): array {
                $data = [];

                foreach ($returnTypeFields as $field) {
                    switch ($field->name) {
                        case 'cursor':
                            $data['cursor'] = Cursor::encode((int) $firstItem + $index);
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
