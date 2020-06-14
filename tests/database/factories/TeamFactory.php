<?php

use Faker\Generator as Faker;
use Tests\Utils\Models\Team;

/** @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Team::class, function (Faker $faker): array {
    return [
        'name' => $faker->name,
    ];
});
