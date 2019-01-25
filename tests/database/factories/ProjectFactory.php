<?php

use Faker\Generator as Faker;
use Tests\Utils\Models\Project;

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Project::class, function (Faker $faker) {
    return [
        'uuid' => $faker->uuid,
    ];
});
