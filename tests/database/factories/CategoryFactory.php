<?php

use Faker\Generator as Faker;
use Tests\Utils\Models\Category;

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Category::class, function (Faker $faker): array {
    return [
        'name' => $faker->name,
    ];
});
