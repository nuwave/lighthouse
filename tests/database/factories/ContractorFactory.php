<?php

use Faker\Generator as Faker;
use Tests\Utils\Models\Contractor;

/* @var \Illuminate\Database\Eloquent\Factory $factory */

$factory->define(Contractor::class, function (Faker $faker): array {
    return [
        'position' => $faker->jobTitle,
    ];
});
