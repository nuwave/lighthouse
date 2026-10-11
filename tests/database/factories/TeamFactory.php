<?php declare(strict_types=1);

use Faker\Generator as Faker;
use Tests\Utils\Models\Team;

/** @var Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Team::class, static fn (Faker $faker): array => [
    'name' => $faker->name,
]);
