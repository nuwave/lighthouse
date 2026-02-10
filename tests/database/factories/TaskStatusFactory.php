<?php declare(strict_types=1);

use Faker\Generator as Faker;

/** @var Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Tests\Utils\Models\PostStatus::class, static fn (Faker $faker): array => [
    'status' => $faker->randomElement(['DONE', 'PENDING']),
]);
