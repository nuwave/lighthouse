<?php

namespace Nuwave\Lighthouse\Pagination;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\AbstractPaginator;
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
        /** @var int|null $firstItem Laravel type-hints are inaccurate here */
        $firstItem = $paginator->firstItem();
        /** @var int|null $lastItem Laravel type-hints are inaccurate here */
        $lastItem = $paginator->lastItem();

        return [
            'hasNextPage' => $paginator->hasMorePages(),
            'hasPreviousPage' => $paginator->currentPage() > 1,
            'startCursor' => null !== $firstItem
                ? Cursor::encode($firstItem)
                : null,
            'endCursor' => null !== $lastItem
                ? Cursor::encode($lastItem)
                : null,
            'total' => $paginator->total(),
            'count' => $paginator->count(),
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
        ];
    }

    /**
     * Resolve edges for connection.
     *
     * @param  array<string, mixed>  $args
     */
    public function edgeResolver(AbstractPaginator $paginator, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Collection
    {
        // We know those types because we manipulated them during PaginationManipulator
        /** @var \GraphQL\Type\Definition\NonNull $nonNullList */
        $nonNullList = $resolveInfo->returnType;
        /** @var \GraphQL\Type\Definition\ObjectType|\GraphQL\Type\Definition\InterfaceType $objectLikeType */
        $objectLikeType = $nonNullList->getWrappedType(true);

        $returnTypeFields = $objectLikeType->getFields();

        /** @var int|null $firstItem Laravel type-hints are inaccurate here */
        $firstItem = $paginator->firstItem();

        /**
         * The return type `static` refers to the wrong class because it is a proxied method call.
         *
         * @var \Illuminate\Support\Collection<mixed> $values
         */
        $values = $paginator->values();

        return $values->map(function ($item, int $index) use ($returnTypeFields, $firstItem): array {
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
