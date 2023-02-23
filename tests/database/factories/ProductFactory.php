<?php declare(strict_types=1);

use Faker\Generator as Faker;
use Tests\Utils\Models\Color;
use Tests\Utils\Models\Product;

/** @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Product::class, function (Faker $faker): array {
    return [
        // @phpstan-ignore-next-line Call to an undefined method Faker\Generator::ean13().
        'barcode' => $faker->ean13(),
        'uuid' => $faker->uuid,
        'color_id' => function () {
            return factory(Color::class)->create()->getKey();
        },
        'name' => $faker->name,
    ];
});
