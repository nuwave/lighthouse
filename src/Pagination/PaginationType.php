<?php

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

    /**
     * @var string One of the constant values in this class
     */
    protected $type;

    /**
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    public function __construct(string $paginationType)
    {
        // TODO remove lowercase and alternate options in v6
        switch (strtolower($paginationType)) {
            case 'default':
            case 'paginator':
                $this->type = self::PAGINATOR;
                break;
            case 'simple':
                $this->type = self::SIMPLE;
                break;
            case 'connection':
            case 'relay':
                $this->type = self::CONNECTION;
                break;
            default:
                throw new DefinitionException("Found invalid pagination type: {$paginationType}");
        }
    }

    public function isPaginator(): bool
    {
        return self::PAGINATOR === $this->type;
    }

    public function isSimple(): bool
    {
        return self::SIMPLE === $this->type;
    }

    public function isConnection(): bool
    {
        return self::CONNECTION === $this->type;
    }

    public function infoFieldName(): string
    {
        switch ($this->type) {
            case self::PAGINATOR:
            case self::SIMPLE:
                return 'paginatorInfo';
            case self::CONNECTION:
                return 'pageInfo';
            default:
                throw new \Exception("infoFieldName is not implemented for pagination type: {$this->type}.");
        }
    }
}
