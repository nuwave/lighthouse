<?php

use Faker\Generator as Faker;

$factory->define(Nuwave\Lighthouse\Tests\Utils\Models\User::class, function (Faker $faker) {
    return [
        'name' => $faker->name,
        'email' => $faker->unique()->safeEmail,
        'password' => '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', // secret
        'created_at' => now(),
        'updated_at' => now(),
    ];
});
