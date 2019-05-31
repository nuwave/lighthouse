<?php

use Tests\Utils\Models\Team;
use Faker\Generator as Faker;

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Team::class, function (Faker $faker): array {
    return [
        'name' => $faker->name,
    ];
});
