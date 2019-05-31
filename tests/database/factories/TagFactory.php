<?php

use Tests\Utils\Models\Tag;
use Faker\Generator as Faker;

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Tag::class, function (Faker $faker): array {
    return [
        'name' => $faker->name,
    ];
});
