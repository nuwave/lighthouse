<?php

$namespace = "Nuwave\\Relay\\Tests\\Support\\Models\\";

$factory->define($namespace.'User', function (Faker\Generator $faker) {
    return [
        'name' => $faker->name,
        'email' => $faker->email,
    ];
});
