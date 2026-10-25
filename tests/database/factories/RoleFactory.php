<?php declare(strict_types=1);

use Faker\Generator as Faker;
use Illuminate\Support\Collection;
use Tests\Utils\Models\ACL;
use Tests\Utils\Models\Role;

/** @var Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Role::class, static fn (Faker $faker): array => [
    'name' => "role_{$faker->unique()->randomNumber()}",
    'bytes' => implode('', array_map(
        fn (int $b): string => chr($b), // @phpstan-ignore argument.type (will be 0-255)
        Collection::times(16, fn (): int => $faker->numberBetween(0, 255))->all(),
    )),
    'acl_id' => factory(ACL::class),
]);
