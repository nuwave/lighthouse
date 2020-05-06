<?php

use Faker\Generator as Faker;
use Tests\Utils\Models\ACL;
use Tests\Utils\Models\Role;

/** @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Role::class, function (Faker $faker): array {
    return [
        'name' => 'role_' . $faker->unique()->randomNumber(),
        'acl_id' => function () {
            return factory(ACL::class)->create()->getKey();
        },
    ];
});
