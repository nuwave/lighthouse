<?php declare(strict_types=1);

use Faker\Generator as Faker;
use Tests\Utils\Models\Color;

/** @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Color::class, function (Faker $faker): array {
    return [
        'name' => $faker->name,
    ];
});
