<?php

use Faker\Generator as Faker;

$factory->define(Tests\Utils\Models\Task::class, function (Faker $faker) {
    return [
        'user_id' => function () {
            return factory(Tests\Utils\Models\User::class)->create()->getKey();
        },
        'name' => $faker->sentence,
        'created_at' => now(),
        'updated_at' => now(),
    ];
});
