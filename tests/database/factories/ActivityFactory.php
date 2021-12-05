<?php

use Faker\Generator as Faker;
use Tests\Utils\Models\Activity;

/** @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Activity::class, function (Faker $faker): array {
    return [
        'user_id' => '',
        'content_id' => '',
        'content_type' => '',
    ];
});
