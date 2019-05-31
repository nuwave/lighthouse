<?php

use Faker\Generator as Faker;
use Tests\Utils\Models\Color;

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Color::class, function (Faker $faker): array {
    return [
        'name' => $faker->name,
    ];
});
