<?php

use Faker\Generator as Faker;

$factory->define(Tests\Utils\Models\Company::class, function (Faker $faker) {
    return [
        'name' => $faker->sentence,
        'created_at' => now(),
        'updated_at' => now(),
    ];
});
