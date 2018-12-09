<?php

use Faker\Generator as Faker;

/** @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Tests\Utils\Models\Company::class, function (Faker $faker) {
    return [
        'name' => $faker->sentence,
    ];
});
