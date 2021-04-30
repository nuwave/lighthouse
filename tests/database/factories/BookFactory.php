<?php

use Faker\Generator as Faker;
use Tests\Utils\Models\Book;

/** @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Book::class, function (Faker $faker): array {
    return [
        'title' => $faker->name,
        'price' => $faker->randomNumber(),
    ];
});
