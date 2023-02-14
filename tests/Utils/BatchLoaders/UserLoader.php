<?php

namespace Tests\Utils\BatchLoaders;

use Nuwave\Lighthouse\Execution\DataLoader\BatchLoader;
use Tests\Utils\Models\User;

final class UserLoader extends BatchLoader
{
    public function resolve(): array
    {
        return User::findMany(array_keys($this->keys))
            ->keyBy('id')
            ->all();
    }
}
