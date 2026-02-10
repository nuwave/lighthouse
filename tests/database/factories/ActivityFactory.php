<?php declare(strict_types=1);

use Faker\Generator as Faker;
use Tests\Utils\Models\Activity;

/** @var Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Activity::class, static fn (Faker $faker): array => [
    'user_id' => '',
    'content_id' => '',
    'content_type' => '',
]);
