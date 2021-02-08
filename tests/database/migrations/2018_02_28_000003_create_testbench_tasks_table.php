<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTestbenchTasksTable extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name')->unique();
            $table->string('guard')
                ->nullable()
                ->comment('The purpose of this property is to collide with a native model method name');

            $table->unsignedInteger('user_id')->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::drop('tasks');
    }
}
