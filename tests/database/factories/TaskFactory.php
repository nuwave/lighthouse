<?php

use Faker\Generator as Faker;
use Tests\Utils\Models\Task;

/** @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Task::class, function (Faker $faker): array {
    return [
        'name' => $faker->unique()->sentence,
        'difficulty' => $faker->numberBetween(1, 10),
    ];
});

$factory->state(Task::class, 'completed', function (): array {
    return [
        'completed_at' => now(),
    ];
});
