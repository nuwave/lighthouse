<?php

namespace Nuwave\Lighthouse\Tests\DataLoader\Support;

use Nuwave\Lighthouse\Tests\Support\Models\Company;
use Nuwave\Lighthouse\Support\DataLoader\GraphQLDataFetcher;

class UserDataFetcher extends GraphQLDataFetcher
{
    /**
     * Available child loaders.
     *
     * @var array
     */
    protected $children = [
        'tasks' => TaskDataFetcher::class,
    ];

    /**
     * Resolve company users.
     *
     * @param  Company $company
     * @param  array $fields
     * @return mixed
     */
    public function companyUsers($company, array $fields)
    {
        return $company->users()->get();
    }
}
