<?php declare(strict_types=1);

use Faker\Generator as Faker;
use Tests\Utils\Models\Contractor;

/* @var \Illuminate\Database\Eloquent\Factory $factory */

$factory->define(Contractor::class, fn (Faker $faker): array => [
    'position' => $faker->word,
]);
