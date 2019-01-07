<?php

namespace Tests\Integration\Models;

use Tests\DBTestCase;
use Tests\Utils\Models\User;
use Illuminate\Support\Facades\DB;

class UserTest extends DBTestCase
{
    /**
     * @test
     */
    public function itCanInsertRecordsIntoTestDB(): void
    {
        factory(User::class, 2)->create();

        $this->assertCount(2, DB::table('users')->get());
    }

    /**
     * @test
     */
    public function itRefreshesDB(): void
    {
        $this->assertCount(0, DB::table('users')->get());
    }
}
