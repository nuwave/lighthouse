<?php

use Faker\Generator as Faker;

$factory->define(Tests\Utils\Models\Product::class, function (Faker $faker) {
    return [
        'barcode' => $faker->ean13,
        'uuid' => $faker->uuid,
        'color_id' => function () {
            return factory(Tests\Utils\Models\Color::class)->create()->getKey();
        },
        'name' => $faker->name,
        'created_at' => now(),
        'updated_at' => now(),
    ];
});
