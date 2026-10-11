<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Pagination;

use Nuwave\Lighthouse\Exceptions\DefinitionException;

/**
 * An enum-like class that contains the supported types of pagination.
 */
class PaginationType
{
    public const PAGINATOR = 'PAGINATOR';

    public const SIMPLE = 'SIMPLE';

    public const CONNECTION = 'CONNECTION';

    /** One of the constant values in this class. */
    protected string $type;

    public function __construct(string $paginationType)
    {
        $this->type = match ($paginationType) {
            self::PAGINATOR => self::PAGINATOR,
            self::SIMPLE => self::SIMPLE,
            self::CONNECTION => self::CONNECTION,
            default => throw new DefinitionException("Found invalid pagination type: {$paginationType}"),
        };
    }

    public function isPaginator(): bool
    {
        return $this->type === self::PAGINATOR;
    }

    public function isSimple(): bool
    {
        return $this->type === self::SIMPLE;
    }

    public function isConnection(): bool
    {
        return $this->type === self::CONNECTION;
    }

    public function infoFieldName(): string
    {
        return match ($this->type) {
            self::PAGINATOR, self::SIMPLE => 'paginatorInfo',
            self::CONNECTION => 'pageInfo',
            default => throw new \Exception("infoFieldName is not implemented for pagination type: {$this->type}."),
        };
    }
}
