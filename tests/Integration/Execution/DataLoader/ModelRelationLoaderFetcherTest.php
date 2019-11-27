<?php

namespace Tests\Integration\Execution\DataLoader;

use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Execution\DataLoader\ModelRelationFetcher;
use Tests\DBTestCase;
use Tests\Utils\Models\Tag;
use Tests\Utils\Models\Task;

class ModelRelationLoaderFetcherTest extends DBTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $task = factory(Task::class)->create();
        $tags = factory(Tag::class, 3)->create();

        $tags->each(function (Tag $tag) use ($task): void {
            DB::table('taggables')->insert([
                'tag_id' => $tag->id,
                'taggable_id' => $task->id,
                'taggable_type' => get_class($task),
            ]);
        });
    }

    public function testGetsPolymorphicRelationship(): void
    {
        /** @var \Tests\Utils\Models\Task $task */
        $task = Task::first();
        $this->assertCount(3, $task->tags);

        $tasks = (new ModelRelationFetcher(Task::all(), ['tags']))
            ->loadRelationsForPage(2)
            ->models();

        $this->assertCount(2, $tasks->first()->tags);
    }
}
