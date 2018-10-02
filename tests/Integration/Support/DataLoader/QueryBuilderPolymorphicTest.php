<?php

namespace Tests\Integration\Support\DataLoader;

use Tests\DBTestCase;
use Tests\Utils\Models\Tag;
use Tests\Utils\Models\Task;
use Illuminate\Support\Facades\DB;

class QueryBuilderPolymorphicTest extends DBTestCase
{
    /**
     * Setup test environment.
     */
    public function setUp()
    {
        parent::setUp();

        $task = factory(Task::class)->create();
        $tags = factory(Tag::class, 3)->create();

        $tags->each(function(Tag $tag) use ($task){
            DB::table('taggables')->insert([
                'tag_id' => $tag->id,
                'taggable_id' => $task->id,
                'taggable_type' => get_class($task),
            ]);
        });
    }

    /**
     * @test
     */
    public function itGetsPolymorphicRelationship()
    {
        /** @var Task $task */
        $task = Task::first();
        $this->assertCount(3, $task->tags);

        $tasks = Task::all();
        $tasks->fetch(['tags' => function ($query) {
            $query->take(2);
        }]);

        $this->assertCount(2, $tasks->first()->tags);
    }
}
