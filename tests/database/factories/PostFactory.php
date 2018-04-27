<?php

use Faker\Generator as Faker;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Team;
use Tests\Utils\Models\User;

$factory->define(Post::class, function (Faker $faker) {
    return [
        'title' => $faker->title,
        'body' => $faker->sentence,
        'user_id' => function () {
            return factory(User::class)->create()->getKey();
        },
    ];
});
