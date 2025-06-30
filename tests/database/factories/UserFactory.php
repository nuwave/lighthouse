<?php declare(strict_types=1);

use Faker\Generator as Faker;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\Team;
use Tests\Utils\Models\User;

/** @var Illuminate\Database\Eloquent\Factory $factory */
$factory->define(User::class, static fn (Faker $faker): array => [
    'company_id' => factory(Company::class),
    'team_id' => factory(Team::class),
    'name' => $faker->name,
    'email' => $faker->unique()->safeEmail,
    'password' => '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', // secret
]);
