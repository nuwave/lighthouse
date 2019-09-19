<?php

namespace Tests\Integration\Models;

use Tests\DBTestCase;
use Tests\Utils\Models\User;
use Illuminate\Support\Facades\DB;

class UserTest extends DBTestCase
{
    public function testCanInsertRecordsIntoTestDB(): void
    {
        factory(User::class, 2)->create();

        $this->assertCount(2, DB::table('users')->get());
    }

    public function testRefreshesDB(): void
    {
        $this->assertCount(0, DB::table('users')->get());
    }
}
