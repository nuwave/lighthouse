<?php

use Faker\Generator as Faker;

$factory->define(Tests\Utils\Models\Team::class, function (Faker $faker) {
    return [
        'name' => $faker->name,
        'created_at' => now(),
        'updated_at' => now(),
    ];
});
