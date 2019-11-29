<?php

use Faker\Generator as Faker;
use Tests\Utils\Models\Brand;

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Brand::class, function (Faker $faker): array {
    return [
        'name' => $faker->name,
    ];
});
