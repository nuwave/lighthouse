<?php

use Faker\Generator as Faker;
use Tests\Utils\Models\Author;

/** @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Author::class, function (Faker $faker): array {
    return [
        'name' => $faker->name,
    ];
});
