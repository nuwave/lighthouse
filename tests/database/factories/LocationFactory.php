<?php declare(strict_types=1);

use Faker\Generator as Faker;
use Tests\Utils\Models\Location;

/** @var Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Location::class, static fn (Faker $faker): array => [
    'extra' => [
        'value' => $faker->word(),
    ],
    'parent_id' => null,
]);
