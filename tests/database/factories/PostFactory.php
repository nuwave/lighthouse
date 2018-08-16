<?php

use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;
use Faker\Generator as Faker;

$factory->define(Post::class, function (Faker $faker) {
    return [
        'title' => $faker->title,
        'body' => $faker->sentence,
        'user_id' => function () {
            return factory(User::class)->create()->getKey();
        },
    ];
});
