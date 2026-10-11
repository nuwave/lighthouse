<?php declare(strict_types=1);

use Faker\Generator as Faker;
use Tests\Utils\Models\Task;

/** @var Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Task::class, static fn (Faker $faker): array => [
    'name' => $faker->unique()->sentence,
    'difficulty' => $faker->numberBetween(1, 10),
]);

$factory->state(Task::class, 'completed', static fn (): array => [
    'completed_at' => now(),
]);
