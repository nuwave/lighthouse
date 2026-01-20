<?php

declare(strict_types=1);

namespace Nuwave\Lighthouse\Pagination;

use Illuminate\Pagination\LengthAwarePaginator;

/** @extends \Illuminate\Pagination\LengthAwarePaginator<array-key, mixed> */
class NegativePerPageLengthAwarePaginator extends LengthAwarePaginator
{
  public function __construct($results, int $total)
  {
    $this->total = $total;
    $this->perPage = $total;
    $this->lastPage = 1;
    $this->currentPage = 1;
    $this->items = $results;
  }
}
