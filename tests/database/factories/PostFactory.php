<?php declare(strict_types=1);

use Faker\Generator as Faker;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

/** @var Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Post::class, static fn (Faker $faker): array => [
    'title' => $faker->title,
    'body' => $faker->sentence,
    'user_id' => $faker->randomElement([factory(User::class), null]),
    'task_id' => factory(Task::class),
    'parent_id' => $faker->randomElement([factory(Post::class), null]),
]);
