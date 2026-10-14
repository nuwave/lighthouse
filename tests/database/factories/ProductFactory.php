<?php declare(strict_types=1);

use Faker\Generator as Faker;
use Tests\Utils\Models\Color;
use Tests\Utils\Models\Product;

/** @var Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Product::class, static fn (Faker $faker): array => [
    'barcode' => $faker->ean13(),
    'uuid' => $faker->uuid,
    'color_id' => factory(Color::class),
    'name' => $faker->name,
]);
