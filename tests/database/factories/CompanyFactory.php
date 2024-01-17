<?php declare(strict_types=1);

use Faker\Generator as Faker;
use Tests\Utils\Models\Company;

/** @var Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Company::class, static fn (Faker $faker): array => [
    'name' => $faker->sentence,
    'uuid' => $faker->uuid,
]);
