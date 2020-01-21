<?php

use Faker\Generator as Faker;
use Tests\Utils\Models\Task;

/** @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Task::class, function (Faker $faker): array {
    return [
        'name' => $faker->unique()->sentence,
    ];
});
