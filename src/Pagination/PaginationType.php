<?php

namespace Nuwave\Lighthouse\Pagination;

use Nuwave\Lighthouse\Exceptions\DefinitionException;

/**
 * An enum-like class that contains the supported types of pagination.
 */
class PaginationType
{
    public const TYPE_PAGINATOR = 'paginator';
    public const PAGINATION_TYPE_CONNECTION = 'connection';

    /**
     * @var string PAGINATION_TYPE_PAGINATOR|PAGINATION_TYPE_CONNECTION
     */
    protected $type;

    /**
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    public function __construct(string $paginationType)
    {
        switch ($paginationType) {
            case 'default':
            case 'paginator':
                $this->type = self::TYPE_PAGINATOR;
                break;
            case 'connection':
            case 'relay':
                $this->type = self::PAGINATION_TYPE_CONNECTION;
                break;
            default:
                throw new DefinitionException(
                    "Found invalid pagination type: {$paginationType}"
                );
        }
    }

    public function isPaginator(): bool
    {
        return $this->type === self::TYPE_PAGINATOR;
    }

    public function isConnection(): bool
    {
        return $this->type === self::PAGINATION_TYPE_CONNECTION;
    }
}
