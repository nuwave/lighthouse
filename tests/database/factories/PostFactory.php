<?php declare(strict_types=1);

use Faker\Generator as Faker;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

/** @var Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Post::class, static fn (Faker $faker): array => [
    'title' => $faker->title,
    'body' => $faker->sentence,
    'user_id' => static fn () => factory(User::class)->create()->getKey(),
    'task_id' => static fn () => factory(Task::class)->create()->getKey(),
    'parent_id' => null,
]);
