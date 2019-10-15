<?php

namespace Tests\Utils\BatchLoaders;

use Tests\Utils\Models\User;
use Nuwave\Lighthouse\Execution\DataLoader\BatchLoader;

class UserLoader extends BatchLoader
{
    public function resolve(): array
    {
        return User::findMany(array_keys($this->keys))
            ->keyBy('id')
            ->all();
    }
}
