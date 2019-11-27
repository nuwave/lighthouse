<?php

use Faker\Generator as Faker;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Task::class, function (Faker $faker): array {
    return [
        'user_id' => function () {
            return factory(User::class)->create()->getKey();
        },
        'name' => $faker->unique()->sentence,
    ];
});
