<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTestbenchRoleUserTable extends Migration
{
    public function up(): void
    {
        Schema::create('role_user', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('meta')->nullable();

            $table->unsignedInteger('user_id');
            $table->unsignedInteger('role_id');
        });
    }

    public function down(): void
    {
        Schema::drop('role_user');
    }
}
