<?php

use Tests\Utils\Models\Hour;
use Tests\Utils\Models\Task;
use Faker\Generator as Faker;

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Hour::class, function (Faker $faker) {
    return [
        'from' => $faker->time('H:i'),
        'to' => $faker->time('H:i'),
        'hourable_id' => Task::all()->random(1)[0]->id,
        'hourable_type' => 'task',
        'workday' => $faker->randomElement([0, 1, 2, 3, 4, 5, 6]),
    ];
});
