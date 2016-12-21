<?php

namespace Nuwave\Lighthouse\Tests\DataLoader\Support;

use Nuwave\Lighthouse\Support\DataLoader\GraphQLDataFetcher;

class CompanyDataFetcher extends GraphQLDataFetcher
{
    /**
     * Available child data fetchers.
     *
     * @var array
     */
    protected $children = [
        'users' => UserDataFetcher::class,
    ];
}
