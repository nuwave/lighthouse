<?php

use Faker\Generator as Faker;
use Tests\Utils\Models\ACL;

/** @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(ACL::class, function (Faker $faker): array {
    return [
        'create_post' => $faker->boolean,
        'read_post' => $faker->boolean,
        'update_post' => $faker->boolean,
        'delete_post' => $faker->boolean,
    ];
});
