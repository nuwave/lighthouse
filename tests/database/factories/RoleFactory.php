<?php declare(strict_types=1);

use Faker\Generator as Faker;
use Tests\Utils\Models\ACL;
use Tests\Utils\Models\Role;

/** @var Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Role::class, static fn (Faker $faker): array => [
    'name' => "role_{$faker->unique()->randomNumber()}",
    'acl_id' => factory(ACL::class),
]);
