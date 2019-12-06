<?php

use Faker\Generator as Faker;
use Tests\Utils\Models\Brand;
use Tests\Utils\Models\Color;
use Tests\Utils\Models\Product;

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Product::class, function (Faker $faker): array {
    return [
        'barcode' => $faker->ean13,
        'uuid' => $faker->uuid,
        'brand_id' => function () {
            return factory(Brand::class)->create()->getKey();
        },
        'color_id' => function () {
            return factory(Color::class)->create()->getKey();
        },
        'name' => $faker->name,
    ];
});
