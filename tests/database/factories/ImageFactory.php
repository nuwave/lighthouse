<?php declare(strict_types=1);

use Faker\Generator as Faker;
use Tests\Utils\Models\Image;

/** @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Image::class, fn (Faker $faker): array => [
    'url' => $faker->url,
]);
