<?php

use Faker\Generator as Faker;
use Tests\Utils\Models\Supplier;

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Supplier::class, function (Faker $faker): array {
    return [
        'name' => $faker->name,
    ];
});
