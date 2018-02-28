<?php

use Faker\Generator as Faker;

$factory->define(Nuwave\Lighthouse\Tests\Utils\Models\Task::class, function (Faker $faker) {
    return [
        'user_id' => function () {
            return factory(Nuwave\Lighthouse\Tests\Utils\Models\User::class)->create()->getKey();
        },
        'created_at' => now(),
        'updated_at' => now(),
    ];
});
