<?php

use Faker\Generator as Faker;
use Tests\Utils\Models\Company;

$factory->define(Tests\Utils\Models\User::class, function (Faker $faker) {
    return [
        'company_id' => function () {
            return factory(Company::class)->create()->getKey();
        },
        'name' => $faker->name,
        'email' => $faker->unique()->safeEmail,
        'password' => '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', // secret
        'created_at' => now(),
        'updated_at' => now(),
    ];
});
