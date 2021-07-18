<?php

use Faker\Generator as Faker;
use Tests\Utils\Models\Location;

/** @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Location::class, function (Faker $faker): array {
    return [
        'extra' => [
            'value' => $faker->word(),
        ],
        'parent_id' => null,
    ];
});
