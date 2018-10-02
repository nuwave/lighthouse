<?php

use Faker\Generator as Faker;

$factory->define(Tests\Utils\Models\Tag::class, function (Faker $faker) {
    return [
        'name' => $faker->name,
    ];
});
