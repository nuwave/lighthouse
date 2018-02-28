<?php

namespace Nuwave\Lighthouse\Tests\Integration\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Tests\DBTestCase;
use Nuwave\Lighthouse\Tests\Utils\Models\User;

class UserTest extends DBTestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function itCanInsertRecordsIntoTestDB()
    {
        factory(User::class, 2)->create();

        $this->assertCount(2, \DB::table('users')->get());
    }

    /**
     * @test
     */
    public function itRefreshesDB()
    {
        $this->assertCount(0, \DB::table('users')->get());
    }
}
