<?php

use Faker\Generator as Faker;
use Tests\Utils\Models\CustomPrimaryKey;

/** @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(CustomPrimaryKey::class, function (Faker $faker): array {
    return [];
});
