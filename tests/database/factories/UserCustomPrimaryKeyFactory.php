<?php

use Faker\Generator as Faker;
use Tests\Utils\Models\UserCustomPrimaryKey;

/** @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(UserCustomPrimaryKey::class, function (Faker $faker): array {
    return [
        'name' => $faker->name,
    ];
});
