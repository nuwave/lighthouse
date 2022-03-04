<?php

namespace Nuwave\Lighthouse\Execution\BatchLoader;

use GraphQL\Deferred;
use App\Models\User;
use App\Posts\PostsService;
use App\Posts\Post;

class UserPostsBatchLoader
{
    /**
     * Map from user ids to users.
     *
     * @var array<int, \App\Models\User>
     */
    protected $users = [];

    /**
     * Map from user ids to posts.
     *
     * @var array<int, array<int, \App\Posts\Post>>
     */
    protected $results = [];

    /**
     * Marks when the actual batch loading happened.
     *
     * @var bool
     */
    protected $hasResolved = false;

    public function load(User $user): Deferred
    {
        $this->users[$user->id] = $user;

        return new Deferred(function () use ($user): array {
            if (! $this->hasResolved) {
                $this->resolve();
            }

            return $this->results[$user->id];
        });
    }

    protected function resolve(): void
    {
        $posts = PostsService::forUsers(array_keys($this->users));

        foreach ($posts as $post) {
            $this->results[$post->user_id][] = $post;
        }

        $this->hasResolved = true;
    }
}
