<?php

use Faker\Generator as Faker;
use Tests\Utils\Models\Comment;
use Tests\Utils\Models\Company;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Team;
use Tests\Utils\Models\User;

$factory->define(Comment::class, function (Faker $faker) {
    return [
        'comment' => $faker->sentence,
        'user_id' => function () {
            return factory(User::class)->create()->getKey();
        },
        'post_id' => function () {
            return factory(Post::class)->create()->getKey();
        },
    ];
});