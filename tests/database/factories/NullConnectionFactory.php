<?php

use Faker\Generator as Faker;
use Tests\Utils\Models\NullConnection;

/** @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(NullConnection::class, function (Faker $faker): array {
    return [];
});
