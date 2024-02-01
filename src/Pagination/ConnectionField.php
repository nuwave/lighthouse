<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Pagination;

use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class ConnectionField
{
    /**
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator<mixed>  $paginator
     *
     * @return array{
     *     hasNextPage: bool,
     *     hasPreviousPage: bool,
     *     startCursor: string|null,
     *     endCursor: string|null,
     *     total: int,
     *     count: int,
     *     currentPage: int,
     *     lastPage: int,
     * }
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
            'startCursor' => $firstItem !== null
                ? Cursor::encode($firstItem)
                : null,
            'endCursor' => $lastItem !== null
                ? Cursor::encode($lastItem)
                : null,
            'total' => $paginator->total(),
            'count' => count($paginator->items()),
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
        ];
    }

    /**
     * @param  \Illuminate\Contracts\Pagination\Paginator<mixed>  $paginator
     * @param  array<string, mixed>  $args
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function edgeResolver(Paginator $paginator, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Collection
    {
        // We know those types because we manipulated them during PaginationManipulator
        $nonNullList = $resolveInfo->returnType;
        assert($nonNullList instanceof NonNull);

        $objectLikeType = $nonNullList->getInnermostType();
        assert($objectLikeType instanceof ObjectType || $objectLikeType instanceof InterfaceType);

        $returnTypeFields = $objectLikeType->getFields();

        /** @var int|null $firstItem Laravel type-hints are inaccurate here */
        $firstItem = $paginator->firstItem();

        $values = new Collection(array_values($paginator->items()));

        return $values->map(static function ($item, int $index) use ($returnTypeFields, $firstItem): array {
            $data = [];
            foreach ($returnTypeFields as $field) {
                switch ($field->name) {
                    case 'cursor':
                        $data['cursor'] = Cursor::encode((int) $firstItem + $index);
                        break;

                    case 'node':
                        $data['node'] = $item;
                        break;

                    case 'pivot':
                        $data['pivot'] = $item->pivot;
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
