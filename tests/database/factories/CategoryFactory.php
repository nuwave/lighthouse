<?php

use Faker\Generator as Faker;

$factory->define(Tests\Utils\Models\Category::class, function (Faker $faker) {
    return [
        'name' => $faker->name,
        'created_at' => now(),
        'updated_at' => now(),
    ];
});
