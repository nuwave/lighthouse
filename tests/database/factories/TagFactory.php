<?php declare(strict_types=1);

use Faker\Generator as Faker;
use Tests\Utils\Models\Tag;

/** @var Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Tag::class, static fn (Faker $faker): array => [
    'name' => $faker->name,
]);
