<?php

use Faker\Generator as Faker;
use Tests\Utils\Models\Image;

/** @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Image::class, function (Faker $faker): array {
    return [
        'url' => $faker->url,
    ];
});
