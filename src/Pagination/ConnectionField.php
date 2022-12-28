<?php

namespace Nuwave\Lighthouse\Pagination;

use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class ConnectionField
{
    /**
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
            'count' => count($paginator->items()),
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     *
     * @return Collection<array<string, mixed>>
     */
    public function edgeResolver(Paginator $paginator, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Collection
    {
        // We know those types because we manipulated them during PaginationManipulator
        $nonNullList = $resolveInfo->returnType;
        assert($nonNullList instanceof NonNull);

        $objectLikeType = $nonNullList->getWrappedType(true);
        assert($objectLikeType instanceof ObjectType || $objectLikeType instanceof InterfaceType);

        $returnTypeFields = $objectLikeType->getFields();

        /** @var int|null $firstItem Laravel type-hints are inaccurate here */
        $firstItem = $paginator->firstItem();

        $values = new Collection(array_values($paginator->items()));

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
