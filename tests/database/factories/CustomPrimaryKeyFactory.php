<?php declare(strict_types=1);

use Faker\Generator as Faker;
use Tests\Utils\Models\CustomPrimaryKey;

/** @var Illuminate\Database\Eloquent\Factory $factory */
$factory->define(CustomPrimaryKey::class, static fn (Faker $faker): array => []);
