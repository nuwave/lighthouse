<?php

use Tests\Utils\Models\ACL;
use Faker\Generator as Faker;

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(ACL::class, function (Faker $faker): array {
    return [
        'create_post' => $faker->boolean,
        'read_post' => $faker->boolean,
        'update_post' => $faker->boolean,
        'delete_post' => $faker->boolean,
    ];
});
