<?php

$namespace = 'Nuwave\\Lighthouse\\Tests\\Support\\Models\\';

$factory->define($namespace.'User', function (Faker\Generator $faker) {
    return [
        'name' => $faker->name,
        'email' => $faker->email,
    ];
});

$factory->define($namespace.'Task', function (Faker\Generator $faker) {
    return [
        'title' => $faker->sentence(4),
        'description' => $faker->paragraph(3),
        'completed' => 0, // false
    ];
});

$factory->define($namespace.'Post', function (Faker\Generator $faker) {
    return [
        'title' => $faker->sentence,
        'content' => $faker->paragraph,
    ];
});

$factory->define($namespace.'Company', function (Faker\Generator $faker) {
    return ['name' => $faker->company];
});
