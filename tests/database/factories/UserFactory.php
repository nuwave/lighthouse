<?php declare(strict_types=1);

use Faker\Generator as Faker;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\Team;
use Tests\Utils\Models\User;

/** @var Illuminate\Database\Eloquent\Factory $factory */
$factory->define(User::class, static fn (Faker $faker): array => [
    'company_id' => static fn () => factory(Company::class)->create()->getKey(),
    'team_id' => static fn () => factory(Team::class)->create()->getKey(),
    'name' => $faker->name,
    'email' => $faker->unique()->safeEmail,
    'password' => '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', // secret
]);
