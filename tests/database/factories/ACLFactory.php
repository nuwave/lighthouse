<?php declare(strict_types=1);

use Faker\Generator as Faker;
use Tests\Utils\Models\ACL;

/** @var Illuminate\Database\Eloquent\Factory $factory */
$factory->define(ACL::class, static fn (Faker $faker): array => [
    'create_post' => $faker->boolean,
    'read_post' => $faker->boolean,
    'update_post' => $faker->boolean,
    'delete_post' => $faker->boolean,
]);
