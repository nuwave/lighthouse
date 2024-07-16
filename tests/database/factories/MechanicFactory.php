<?php declare(strict_types=1);

use Faker\Generator as Faker;

/** @var Illuminate\Database\Eloquent\Factory $factory */
$factory->define(\Tests\Utils\Models\Mechanic::class, static fn (Faker $faker): array => [
    'name' => $faker->title,
]);
