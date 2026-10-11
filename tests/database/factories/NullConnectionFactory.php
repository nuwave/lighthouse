<?php declare(strict_types=1);

use Faker\Generator as Faker;
use Tests\Utils\Models\NullConnection;

/** @var Illuminate\Database\Eloquent\Factory $factory */
$factory->define(NullConnection::class, static fn (Faker $faker): array => []);
