<?php

use Faker\Generator as Faker;

$factory->define(Tests\Utils\Models\Task::class, function (Faker $faker) {
    return [
        'user_id' => function () {
            return factory(Tests\Utils\Models\User::class)->create()->getKey();
        },
        'created_at' => now(),
        'updated_at' => now(),
    ];
});
