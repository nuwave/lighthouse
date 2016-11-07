<?php

namespace Nuwave\Lighthouse\Tests\DataLoader\Support;

use Nuwave\Lighthouse\Support\DataLoader\GraphQLDataLoader;
use Nuwave\Lighthouse\Tests\Support\Models\Company;

class UserDataLoader extends GraphQLDataLoader
{
    /**
     * Available child loaders.
     *
     * @var array
     */
    protected $children = [
        'tasks' => TaskDataLoader::class,
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
