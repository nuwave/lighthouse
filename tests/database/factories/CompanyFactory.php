<?php

use Faker\Generator as Faker;
use Tests\Utils\Models\Company;

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Company::class, function (Faker $faker): array {
    return [
        'name' => $faker->sentence,
    ];
});
