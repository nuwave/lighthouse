<?php declare(strict_types=1);

use Faker\Generator as Faker;
use Tests\Utils\Models\Employee;

/* @var \Illuminate\Database\Eloquent\Factory $factory */

$factory->define(Employee::class, static fn (Faker $faker): array => [
    'position' => $faker->word,
]);
