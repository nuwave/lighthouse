<?php

use Faker\Generator as Faker;
use Tests\Utils\Models\Employee;

/* @var \Illuminate\Database\Eloquent\Factory $factory */

$factory->define(Employee::class, function (Faker $faker): array {
    return [
        'position' => $faker->jobTitle,
    ];
});
