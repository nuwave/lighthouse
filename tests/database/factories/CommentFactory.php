<?php declare(strict_types=1);

use Faker\Generator as Faker;
use Tests\Utils\Models\Comment;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;

/** @var Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Comment::class, static fn (Faker $faker): array => [
    'comment' => $faker->sentence,
    'user_id' => factory(User::class),
    'post_id' => factory(Post::class),
]);
