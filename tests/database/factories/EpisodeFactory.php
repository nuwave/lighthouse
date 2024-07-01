<?php declare(strict_types=1);

use Faker\Generator as Faker;

/** @var Illuminate\Database\Eloquent\Factory $factory */
$factory->define(\Tests\Utils\Models\Episode::class, static fn (Faker $faker): array => [
    'title' => $faker->title,
    'schedule_at' => $faker->date(),
]);
