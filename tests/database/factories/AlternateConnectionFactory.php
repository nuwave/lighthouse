<?php declare(strict_types=1);

use Faker\Generator as Faker;
use Tests\Utils\Models\AlternateConnection;

/** @var Illuminate\Database\Eloquent\Factory $factory */
$factory->define(AlternateConnection::class, static fn (Faker $faker): array => []);
