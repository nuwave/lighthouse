<?php

namespace Nuwave\Lighthouse\Tests\DataLoader\Support;

use Nuwave\Lighthouse\Support\DataLoader\GraphQLDataLoader;

class CompanyDataLoader extends GraphQLDataLoader
{
    /**
     * Available child loaders.
     *
     * @var array
     */
    protected $children = [
        'users' => UserDataLoader::class,
    ];
}
