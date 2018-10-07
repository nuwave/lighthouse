<?php

use Faker\Generator as Faker;
use Tests\Utils\Models\ACL;

$factory->define(ACL::class, function (Faker $faker) {
    return [
        'create_post' => $faker->boolean,
        'read_post'   => $faker->boolean,
        'update_post' => $faker->boolean,
        'delete_post' => $faker->boolean,
    ];
});