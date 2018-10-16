<?php

use Tests\Utils\Models\ACL;
use Faker\Generator as Faker;

$factory->define(ACL::class, function (Faker $faker) {
    return [
        'create_post' => $faker->boolean,
        'read_post'   => $faker->boolean,
        'update_post' => $faker->boolean,
        'delete_post' => $faker->boolean,
    ];
});
