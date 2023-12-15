<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Pagination;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

/** @extends \Illuminate\Pagination\Paginator<mixed> */
class ZeroPerPagePaginator extends Paginator
{
    public function __construct(int $page)
    {
        $this->perPage = 0;
        $this->currentPage = $page;
        $this->items = new Collection();
    }
}
