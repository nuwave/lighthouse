<?php

namespace Nuwave\Lighthouse\Tests\DataLoader\Support;

use Nuwave\Lighthouse\Support\DataLoader\GraphQLDataLoader;
use Nuwave\Lighthouse\Tests\Support\Models\Company;

class UserDataLoader extends GraphQLDataLoader
{
    /**
     * Get short name of data loader.
     *
     * @return string
     */
    public function getName()
    {
        return 'user';
    }

    /**
     * Resolve company users.
     *
     * @param  Company $company
     * @param  array $fields
     * @return mixed
     */
    public function companyUsers($company, array $fields)
    {
        return null;
    }
}
